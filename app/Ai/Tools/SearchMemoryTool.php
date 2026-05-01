<?php

namespace App\Ai\Tools;

use App\Domains\Search\Support\Vector;
use App\Models\AgentMemory;
use App\Services\AI\VectorEmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchMemoryTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Search long-term agent memory. Use this for relevant global knowledge or private user memory before answering questions that depend on prior facts or instructions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $validated = $this->validateInput($request->all(), [
            'search_query' => ['required', 'string', 'max:2000'],
        ]);

        $queryEmbedding = app(VectorEmbeddingService::class)->generate($validated['search_query']);
        $memories = AgentMemory::query()
            ->where(function ($builder) use ($user): void {
                $builder->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->get(['content', 'embedding'])
            ->sortByDesc(fn (AgentMemory $memory): float => Vector::cosineSimilarity($queryEmbedding, $memory->embedding))
            ->take(3)
            ->values();

        if ($memories->isEmpty()) {
            return 'No relevant memories found.';
        }

        return "Relevant memories:\n" . $memories
            ->pluck('content')
            ->values()
            ->map(static fn (string $content, int $index): string => ($index + 1) . '. ' . $content)
            ->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search_query' => $schema->string()
                ->description('A natural language query describing the fact, instruction, or knowledge to retrieve from memory.')
                ->required(),
        ];
    }
}
