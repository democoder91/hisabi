<?php

namespace App\Domains\Search\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Models\SearchableRecord;
use App\Domains\Search\Support\Vector;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Hybrid search over the `searchable_records` index.
 *
 * Each call:
 *   1. Loads candidate index rows for the user + type.
 *   2. Scores each row with: (substring_match ? 1000 : 0) + cosine_similarity.
 *   3. Returns the unique source ids in descending score order.
 *
 * If embeddings cannot be generated for the query, the service degrades to
 * substring-only matching against the indexed content (and the AI tools /
 * services will fall back to LIKE on the source columns).
 */
class SemanticSearchService
{
    /**
     * Score boost applied to rows whose indexed content contains the query as a substring.
     * Large enough that any substring match outranks any pure-semantic match.
     */
    private const SUBSTRING_BOOST = 1000.0;

    /**
     * Minimum cosine similarity to consider a record relevant when no substring match exists.
     */
    private const SEMANTIC_THRESHOLD = 0.20;

    /**
     * @return array<int, int>
     */
    public function searchAccountIds(User $user, string $query, int $limit = 25): array
    {
        return $this->searchIds($user, Account::class, $query, $limit);
    }

    /**
     * @return array<int, int>
     */
    public function searchTransactionIds(User $user, string $query, int $limit = 50): array
    {
        return $this->searchIds($user, Transaction::class, $query, $limit);
    }

    /**
     * @return array<int, int>
     */
    public function searchBudgetIds(User $user, string $query, int $limit = 25): array
    {
        return $this->searchIds($user, Budget::class, $query, $limit);
    }

    /**
     * @param  class-string  $type
     * @return array<int, int>
     */
    private function searchIds(User $user, string $type, string $query, int $limit): array
    {
        $query = trim($query);

        if ($query === '' || $limit <= 0) {
            return [];
        }

        $rows = SearchableRecord::query()
            ->where('user_id', $user->id)
            ->where('searchable_type', (new $type)->getMorphClass())
            ->get(['searchable_id', 'content', 'embedding']);

        if ($rows->isEmpty()) {
            return [];
        }

        $queryEmbedding = $this->embedQuery($query);
        $needle = mb_strtolower($query);

        $bestPerId = [];

        foreach ($rows as $row) {
            $content = (string) $row->content;
            $hasSubstring = $needle !== '' && str_contains(mb_strtolower($content), $needle);

            $similarity = 0.0;
            if ($queryEmbedding !== null && is_array($row->embedding) && $row->embedding !== []) {
                $similarity = Vector::cosineSimilarity($queryEmbedding, $row->embedding);
            }

            if (! $hasSubstring && $similarity < self::SEMANTIC_THRESHOLD) {
                continue;
            }

            $score = ($hasSubstring ? self::SUBSTRING_BOOST : 0.0) + $similarity;
            $sourceId = (int) $row->searchable_id;

            if (! isset($bestPerId[$sourceId]) || $bestPerId[$sourceId] < $score) {
                $bestPerId[$sourceId] = $score;
            }
        }

        if ($bestPerId === []) {
            return [];
        }

        arsort($bestPerId);

        return array_slice(array_keys($bestPerId), 0, $limit);
    }

    /**
     * Embed the user-supplied query, returning a normalized vector or null on failure.
     *
     * @return array<int, float>|null
     */
    private function embedQuery(string $query): ?array
    {
        try {
            $response = Embeddings::for([$query])->generate();
        } catch (Throwable $exception) {
            Log::warning('Failed to embed semantic search query.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $vector = $response->first();

        if (! is_array($vector) || $vector === []) {
            return null;
        }

        return Vector::normalize($vector);
    }
}
