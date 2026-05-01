<?php

use App\Ai\Tools\RememberUserFactTool;
use App\Ai\Tools\SearchMemoryTool;
use App\Models\AgentMemory;
use App\Models\User;
use App\Services\AI\VectorEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function fakeEmbedding(float $activeValue = 1.0, int $activeIndex = 0): array
{
    $vector = array_fill(0, 768, 0.0);
    $vector[$activeIndex] = $activeValue;

    return $vector;
}

it('returns an embedding array from the ollama service', function () {
    $expected = fakeEmbedding(1.0, 3);

    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => $expected,
        ]),
    ]);

    $embedding = app(VectorEmbeddingService::class)->generate('remember this phrase');

    expect($embedding)->toBe($expected);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:11434/api/embeddings'
            && $request['model'] === 'nomic-embed-text'
            && $request['prompt'] === 'remember this phrase';
    });
});

it('does not leak another users private memory', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AgentMemory::query()->create([
        'user_id' => $userB->id,
        'type' => AgentMemory::TYPE_USER_CONTEXT,
        'content' => 'User B private memory',
        'embedding' => fakeEmbedding(1.0, 9),
    ]);

    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => fakeEmbedding(1.0, 9),
        ]),
    ]);

    $result = $this->actingAs($userA)
        ->app->make(SearchMemoryTool::class)
        ->handle(new Request([
            'search_query' => 'private memory',
        ]));

    expect($result)->toBe('No relevant memories found.');
});

it('returns global memory to the authenticated user', function () {
    $user = User::factory()->create();

    AgentMemory::query()->create([
        'user_id' => null,
        'type' => AgentMemory::TYPE_APP_INSTRUCTION,
        'content' => 'Global accounting rule',
        'embedding' => fakeEmbedding(1.0, 7),
    ]);

    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => fakeEmbedding(1.0, 7),
        ]),
    ]);

    $result = $this->actingAs($user)
        ->app->make(SearchMemoryTool::class)
        ->handle(new Request([
            'search_query' => 'accounting rule',
        ]));

    expect($result)->toContain('Global accounting rule');
});

it('stores a remembered fact for the authenticated user', function () {
    $user = User::factory()->create();

    Http::fake([
        'http://localhost:11434/api/embeddings' => Http::response([
            'embedding' => fakeEmbedding(1.0, 4),
        ]),
    ]);

    $result = $this->actingAs($user)
        ->app->make(RememberUserFactTool::class)
        ->handle(new Request([
            'fact_to_remember' => 'Prefers quarterly summaries.',
        ]));

    expect($result)->toBe('User fact remembered successfully.');

    $this->assertDatabaseHas('agent_memories', [
        'user_id' => $user->id,
        'type' => AgentMemory::TYPE_USER_CONTEXT,
        'content' => 'Prefers quarterly summaries.',
    ]);
});
