<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationCostService
{
    public function currency(): string
    {
        return (string) config('ai.costs.currency', 'USD');
    }

    public function totalCostForUsers(iterable $userIds): array
    {
        $normalizedUserIds = collect($userIds)
            ->filter(fn (mixed $userId): bool => $userId !== null)
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedUserIds === []) {
            return [];
        }

        $totals = array_fill_keys($normalizedUserIds, 0.0);

        $messages = DB::table('agent_conversation_messages as messages')
            ->join('agent_conversations as conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereIn('conversations.user_id', $normalizedUserIds)
            ->where('messages.role', 'assistant')
            ->get([
                'conversations.user_id',
                'messages.usage',
                'messages.meta',
            ]);

        foreach ($messages as $message) {
            $userId = (int) $message->user_id;
            $totals[$userId] = ($totals[$userId] ?? 0.0) + $this->calculateMessageCost($message->usage, $message->meta);
        }

        return collect($totals)
            ->map(fn (float $total): float => $this->normalizeAmount($total))
            ->all();
    }

    public function conversationBreakdownForUser(User $user): array
    {
        $conversations = DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'title',
                'updated_at',
            ]);

        if ($conversations->isEmpty()) {
            return [
                'currency' => $this->currency(),
                'totalCost' => 0.0,
                'totalTurns' => 0,
                'conversationCount' => 0,
                'conversations' => [],
            ];
        }

        $conversationData = $conversations->mapWithKeys(function (object $conversation): array {
            return [
                $conversation->id => [
                    'id' => $conversation->id,
                    'title' => (string) $conversation->title,
                    'turns' => 0,
                    'cost' => 0.0,
                    'updatedAt' => $conversation->updated_at,
                ],
            ];
        })->all();

        $messages = DB::table('agent_conversation_messages')
            ->whereIn('conversation_id', array_keys($conversationData))
            ->orderBy('created_at')
            ->get([
                'conversation_id',
                'role',
                'usage',
                'meta',
            ]);

        foreach ($messages as $message) {
            if (! isset($conversationData[$message->conversation_id])) {
                continue;
            }

            if ($message->role === 'user') {
                $conversationData[$message->conversation_id]['turns']++;

                continue;
            }

            if ($message->role === 'assistant') {
                $conversationData[$message->conversation_id]['cost'] += $this->calculateMessageCost($message->usage, $message->meta);
            }
        }

        $conversationRows = collect($conversationData)
            ->map(function (array $conversation): array {
                return [
                    'id' => $conversation['id'],
                    'title' => $conversation['title'],
                    'turns' => $conversation['turns'],
                    'cost' => $this->normalizeAmount($conversation['cost']),
                    'updatedAt' => $conversation['updatedAt'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['cost'] === $right['cost']) {
                    return strcmp((string) $right['updatedAt'], (string) $left['updatedAt']);
                }

                return $left['cost'] < $right['cost'] ? 1 : -1;
            })
            ->values();

        return [
            'currency' => $this->currency(),
            'totalCost' => $this->normalizeAmount((float) $conversationRows->sum('cost')),
            'totalTurns' => (int) $conversationRows->sum('turns'),
            'conversationCount' => $conversationRows->count(),
            'conversations' => $conversationRows->all(),
        ];
    }

    private function calculateMessageCost(?string $usageJson, ?string $metaJson): float
    {
        $usage = $this->decodeJson($usageJson);

        if ($usage === []) {
            return 0.0;
        }

        $meta = $this->decodeJson($metaJson);
        $provider = $this->resolveProvider($meta['provider'] ?? null);
        $model = $this->resolveModel($provider, $meta['model'] ?? null);
        $pricing = $this->resolvePricing($provider, $model);

        if ($pricing === null) {
            return 0.0;
        }

        $promptTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completionTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $cacheWriteInputTokens = max(0, (int) ($usage['cache_write_input_tokens'] ?? 0));
        $cacheReadInputTokens = max(0, (int) ($usage['cache_read_input_tokens'] ?? 0));
        $reasoningTokens = max(0, (int) ($usage['reasoning_tokens'] ?? 0));

        return ($promptTokens / 1_000_000) * (float) ($pricing['input_per_million'] ?? 0)
            + ($completionTokens / 1_000_000) * (float) ($pricing['output_per_million'] ?? 0)
            + ($cacheWriteInputTokens / 1_000_000) * (float) ($pricing['cache_write_input_per_million'] ?? 0)
            + ($cacheReadInputTokens / 1_000_000) * (float) ($pricing['cache_read_input_per_million'] ?? 0)
            + ($reasoningTokens / 1_000_000) * (float) ($pricing['reasoning_per_million'] ?? 0);
    }

    private function resolveProvider(mixed $provider): string
    {
        $providerName = Str::lower(trim((string) $provider));

        if ($providerName !== '') {
            return $providerName;
        }

        return Str::lower((string) config('ai.default', 'openai'));
    }

    private function resolveModel(string $provider, mixed $model): string
    {
        $resolvedModel = Str::lower(trim((string) $model));

        if ($resolvedModel !== '') {
            return $resolvedModel;
        }

        return Str::lower((string) config("ai.providers.{$provider}.models.text.default", ''));
    }

    private function resolvePricing(string $provider, string $model): ?array
    {
        $providerPricing = config("ai.costs.providers.{$provider}", []);

        if (! is_array($providerPricing) || $providerPricing === []) {
            return null;
        }

        if ($model !== '' && isset($providerPricing[$model]) && is_array($providerPricing[$model])) {
            return $providerPricing[$model];
        }

        foreach ($providerPricing as $configuredModel => $pricing) {
            if (! is_array($pricing)) {
                continue;
            }

            $configuredModelName = Str::lower((string) $configuredModel);

            if ($model !== '' && (
                $model === $configuredModelName
                || str_starts_with($model, $configuredModelName . '-')
                || str_starts_with($model, $configuredModelName . '.')
            )) {
                return $pricing;
            }
        }

        return null;
    }

    private function decodeJson(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 6);
    }
}