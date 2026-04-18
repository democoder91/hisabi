<?php

use App\Ai\Agents\HisabiAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uses the configured zai default model when prompting', function () {
    config()->set('ai.providers.zai.models.text.default', 'glm-configured-test-model');

    Http::fake([
        'https://api.z.ai/api/paas/v4/chat/completions' => Http::response([
            'id' => 'chatcmpl-test',
            'model' => 'glm-configured-test-model',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello from ZAI',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 3,
                'total_tokens' => 13,
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = (new HisabiAgent($user))->prompt('Say hello');

    expect($response->text)->toBe('Hello from ZAI');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.z.ai/api/paas/v4/chat/completions'
            && $request['model'] === config('ai.providers.zai.models.text.default');
    });
});