<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai/transcribe/token');

        $response->assertStatus(401);
    }

    public function test_it_returns_token_from_elevenlabs(): void
    {
        config(['ai.providers.eleven.key' => 'test-api-key']);

        Http::fake([
            'api.elevenlabs.io/v1/single-use-token/realtime_scribe' => Http::response([
                'token' => 'sutkn_test_token_123',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe/token');

        $response->assertStatus(200)
            ->assertJsonStructure(['token'])
            ->assertJson(['token' => 'sutkn_test_token_123']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.elevenlabs.io/v1/single-use-token/realtime_scribe'
                && $request->header('xi-api-key')[0] === 'test-api-key';
        });
    }

    public function test_it_returns_503_when_api_key_not_configured(): void
    {
        config(['ai.providers.eleven.key' => null]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe/token');

        $response->assertStatus(503)
            ->assertJson(['error' => 'Speech-to-text is not configured.']);
    }

    public function test_it_returns_502_when_elevenlabs_fails(): void
    {
        config(['ai.providers.eleven.key' => 'test-api-key']);

        Http::fake([
            'api.elevenlabs.io/v1/single-use-token/realtime_scribe' => Http::response([
                'error' => 'unauthorized',
            ], 401),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe/token');

        $response->assertStatus(502)
            ->assertJson(['error' => 'Failed to generate transcription token.']);
    }

    public function test_it_transcribes_an_uploaded_audio_file(): void
    {
        config(['ai.providers.openai.key' => 'openai-test-key']);
        config(['ai.providers.openai.url' => 'https://api.openai.com/v1']);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Coffee purchase for 18 dirhams',
                'language' => 'en',
                'duration' => 3.2,
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe', [
                'audio' => UploadedFile::fake()->create('memo.mp3', 128, 'audio/mpeg'),
            ]);

        $response->assertOk()
            ->assertJson([
                'text' => 'Coffee purchase for 18 dirhams',
                'language' => 'en',
                'duration' => 3.2,
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
                && $request->hasHeader('Authorization', 'Bearer openai-test-key')
                && str_contains((string) $request->body(), 'whisper-1');
        });
    }

    public function test_it_returns_503_when_upload_transcription_is_not_configured(): void
    {
        config(['ai.providers.openai.key' => null]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe', [
                'audio' => UploadedFile::fake()->create('memo.mp3', 64, 'audio/mpeg'),
            ]);

        $response->assertStatus(503)
            ->assertJson(['error' => 'Transcription is not configured.']);
    }

    public function test_it_returns_502_when_upload_transcription_fails(): void
    {
        config(['ai.providers.openai.key' => 'openai-test-key']);
        config(['ai.providers.openai.url' => 'https://api.openai.com/v1']);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'error' => 'provider down',
            ], 500),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe', [
                'audio' => UploadedFile::fake()->create('memo.mp3', 64, 'audio/mpeg'),
            ]);

        $response->assertStatus(502)
            ->assertJson(['error' => 'Failed to transcribe audio.']);
    }
}
