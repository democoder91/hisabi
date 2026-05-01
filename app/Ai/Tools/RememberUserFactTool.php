<?php

namespace App\Ai\Tools;

use App\Models\AgentMemory;
use App\Services\AI\VectorEmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberUserFactTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Remember a new user-specific fact for future conversations. Use this when the user explicitly asks you to remember a preference, context detail, or terminology.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->authenticatedUser();
        $validated = $this->validateInput($request->all(), [
            'fact_to_remember' => ['required', 'string', 'max:5000'],
        ]);

        AgentMemory::query()->create([
            'user_id' => $user->id,
            'type' => AgentMemory::TYPE_USER_CONTEXT,
            'content' => $validated['fact_to_remember'],
            'embedding' => app(VectorEmbeddingService::class)->generate($validated['fact_to_remember']),
        ]);

        return 'User fact remembered successfully.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fact_to_remember' => $schema->string()
                ->description('The user-specific fact, preference, or context that should be saved for future conversations.')
                ->required(),
        ];
    }
}
