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
        config(['ai.providers.eleven.url' => 'https://api.elevenlabs.io/v1']);

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
        config(['ai.providers.eleven.url' => 'https://api.elevenlabs.io/v1']);

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
        config(['ai.providers.eleven.key' => 'test-api-key']);
        config(['ai.providers.eleven.url' => 'https://api.elevenlabs.io/v1']);

        Http::fake([
            'api.elevenlabs.io/v1/speech-to-text' => Http::response([
                'text' => 'Coffee purchase for 18 dirhams',
                'language_code' => 'en',
                'audio_duration_secs' => 3.2,
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
            return $request->url() === 'https://api.elevenlabs.io/v1/speech-to-text'
                && $request->header('xi-api-key')[0] === 'test-api-key'
                && str_contains((string) $request->body(), 'scribe_v2');
        });
    }

    public function test_it_accepts_webm_audio_detected_as_video_container(): void
    {
        config(['ai.providers.eleven.key' => 'test-api-key']);
        config(['ai.providers.eleven.url' => 'https://api.elevenlabs.io/v1']);

        Http::fake([
            'api.elevenlabs.io/v1/speech-to-text' => Http::response([
                'text' => 'Taxi fare',
                'language_code' => 'en',
                'audio_duration_secs' => 2.1,
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe', [
                'audio' => UploadedFile::fake()->create('memo.webm', 128, 'video/webm'),
            ]);

        $response->assertOk()
            ->assertJson([
                'text' => 'Taxi fare',
                'language' => 'en',
                'duration' => 2.1,
            ]);
    }

    public function test_it_returns_503_when_upload_transcription_is_not_configured(): void
    {
        config(['ai.providers.eleven.key' => null]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/transcribe', [
                'audio' => UploadedFile::fake()->create('memo.mp3', 64, 'audio/mpeg'),
            ]);

        $response->assertStatus(503)
            ->assertJson(['error' => 'Transcription is not configured.']);
    }

    public function test_it_returns_502_when_upload_transcription_fails(): void
    {
        config(['ai.providers.eleven.key' => 'test-api-key']);
        config(['ai.providers.eleven.url' => 'https://api.elevenlabs.io/v1']);

        Http::fake([
            'api.elevenlabs.io/v1/speech-to-text' => Http::response([
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
