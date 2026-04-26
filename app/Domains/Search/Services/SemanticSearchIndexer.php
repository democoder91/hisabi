<?php

namespace App\Domains\Search\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Extractors\SearchableExtractor;
use App\Domains\Search\Models\SearchableRecord;
use App\Domains\Search\Support\SearchableDocument;
use App\Domains\Search\Support\Vector;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class SemanticSearchIndexer
{
    public function __construct(
        private readonly SearchableExtractor $extractor,
    ) {
    }

    /**
     * Index a single source model. Generates embeddings for any new or changed
     * content slices and removes index rows that no longer apply.
     */
    public function index(Model $model): void
    {
        $userId = $this->resolveUserId($model);

        if ($userId === null) {
            return;
        }

        $documents = $this->extractor->extract($model);

        $type = $model->getMorphClass();
        $id = (int) $model->getKey();

        if ($documents === []) {
            $this->deleteFor($model);

            return;
        }

        $existing = SearchableRecord::query()
            ->where('searchable_type', $type)
            ->where('searchable_id', $id)
            ->get()
            ->keyBy(fn (SearchableRecord $record): string => $this->slotKey($record->field, $record->locale));

        $desiredSlots = [];
        $documentsNeedingEmbedding = [];

        foreach ($documents as $document) {
            $slot = $this->slotKey($document->field, $document->locale);
            $desiredSlots[$slot] = $document;

            $current = $existing->get($slot);

            if ($current && $current->content === $document->content && is_array($current->embedding) && $current->embedding !== []) {
                continue;
            }

            $documentsNeedingEmbedding[$slot] = $document;
        }

        $embeddingsBySlot = $this->generateEmbeddingsFor($documentsNeedingEmbedding);

        if ($embeddingsBySlot === null) {
            return;
        }

        $now = now();

        foreach ($desiredSlots as $slot => $document) {
            $current = $existing->get($slot);

            if (isset($embeddingsBySlot[$slot])) {
                $payload = $embeddingsBySlot[$slot];

                SearchableRecord::query()->updateOrCreate(
                    [
                        'searchable_type' => $type,
                        'searchable_id' => $id,
                        'field' => $document->field,
                        'locale' => $document->locale,
                    ],
                    [
                        'user_id' => $userId,
                        'content' => $document->content,
                        'embedding' => $payload['embedding'],
                        'embedding_provider' => $payload['provider'],
                        'embedding_model' => $payload['model'],
                        'embedding_dimensions' => count($payload['embedding']),
                        'embedded_at' => $now,
                    ],
                );

                continue;
            }

            if ($current && (int) $current->user_id !== $userId) {
                $current->forceFill(['user_id' => $userId])->save();
            }
        }

        $obsolete = $existing->keys()->diff(array_keys($desiredSlots));

        if ($obsolete->isNotEmpty()) {
            SearchableRecord::query()
                ->where('searchable_type', $type)
                ->where('searchable_id', $id)
                ->whereIn('id', $existing->only($obsolete->all())->pluck('id')->all())
                ->delete();
        }
    }

    /**
     * Remove every index row that belongs to the given source model.
     */
    public function deleteFor(Model $model): void
    {
        SearchableRecord::query()
            ->where('searchable_type', $model->getMorphClass())
            ->where('searchable_id', (int) $model->getKey())
            ->delete();
    }

    /**
     * Generate embeddings for the supplied documents.
     *
     * Returns an associative array keyed by slot, or null when generation
     * fails (caller should keep existing index rows and rely on LIKE fallback).
     *
     * @param  array<string, SearchableDocument>  $documents
     * @return array<string, array{embedding: array<int, float>, provider: ?string, model: ?string}>|null
     */
    private function generateEmbeddingsFor(array $documents): ?array
    {
        if ($documents === []) {
            return [];
        }

        $slots = array_keys($documents);
        $inputs = array_values(array_map(static fn (SearchableDocument $doc): string => $doc->content, $documents));

        try {
            $response = Embeddings::for($inputs)->generate();
        } catch (Throwable $exception) {
            Log::warning('Failed to generate semantic search embeddings.', [
                'inputs' => count($inputs),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $vectors = $response->embeddings;
        $provider = $response->meta->provider ?? null;
        $model = $response->meta->model ?? null;

        $result = [];

        foreach ($slots as $index => $slot) {
            $vector = $vectors[$index] ?? null;

            if (! is_array($vector) || $vector === []) {
                continue;
            }

            $result[$slot] = [
                'embedding' => Vector::normalize($vector),
                'provider' => $provider,
                'model' => $model,
            ];
        }

        return $result;
    }

    private function slotKey(string $field, ?string $locale): string
    {
        return $field . '|' . ($locale ?? '');
    }

    private function resolveUserId(Model $model): ?int
    {
        if ($model instanceof Account || $model instanceof Budget) {
            return $model->user_id ? (int) $model->user_id : null;
        }

        if ($model instanceof Transaction) {
            $userId = $model->user_id;

            if ($userId) {
                return (int) $userId;
            }

            $sourceAccount = $model->fromAccount ?? $model->account;

            if ($sourceAccount && $sourceAccount->user_id) {
                return (int) $sourceAccount->user_id;
            }
        }

        return null;
    }
}
