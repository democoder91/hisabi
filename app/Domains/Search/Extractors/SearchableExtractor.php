<?php

namespace App\Domains\Search\Extractors;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Support\SearchableDocument;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

class SearchableExtractor
{
    /**
     * Extract a list of searchable documents for the given model.
     *
     * @return array<int, SearchableDocument>
     */
    public function extract(Model $model): array
    {
        if ($model instanceof Account) {
            return $this->extractTranslatableName($model);
        }

        if ($model instanceof Budget) {
            return $this->extractTranslatableName($model);
        }

        if ($model instanceof Transaction) {
            return $this->extractTransaction($model);
        }

        return [];
    }

    /**
     * @return array<int, SearchableDocument>
     */
    private function extractTranslatableName(Account|Budget $model): array
    {
        $documents = [];
        $translations = $model->getSafeNameTranslations();

        foreach ($translations as $locale => $value) {
            if (! is_string($value)) {
                continue;
            }

            $content = trim($value);

            if ($content === '') {
                continue;
            }

            $documents[] = new SearchableDocument(
                field: 'name',
                locale: is_string($locale) && $locale !== '' ? $locale : null,
                content: $content,
            );
        }

        return $documents;
    }

    /**
     * @return array<int, SearchableDocument>
     */
    private function extractTransaction(Transaction $transaction): array
    {
        $documents = [];
        $note = is_string($transaction->note) ? trim($transaction->note) : '';
        $description = is_string($transaction->description) ? trim($transaction->description) : '';

        if ($note !== '') {
            $documents[] = new SearchableDocument(
                field: 'note',
                locale: null,
                content: $note,
            );
        }

        if ($description !== '' && $description !== $note) {
            $documents[] = new SearchableDocument(
                field: 'description',
                locale: null,
                content: $description,
            );
        }

        return $documents;
    }
}
