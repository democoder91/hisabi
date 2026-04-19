<?php

namespace App\Services\Finance;

use Illuminate\Support\Arr;

class AiMigrationPromptBuilder
{
    /**
     * @param  array<string, mixed>|object  $legacyTransaction
     */
    public function build($legacyTransaction): string
    {
        $record = (array) $legacyTransaction;

        $date = (string) ($record['date'] ?? $record['created_at'] ?? now()->toDateString());
        $amount = number_format((float) ($record['amount'] ?? 0), 2, '.', '');
        $currency = strtoupper((string) ($record['currency'] ?? config('hisabi.currency', 'EGP')));
        $description = trim((string) ($record['description'] ?? $record['note'] ?? 'No description'));
        $accountName = $this->normalizeName($record['account_name'] ?? null) ?? 'Unknown account';
        $categoryName = $this->normalizeName($record['category_name'] ?? null) ?? 'Uncategorized';

        return sprintf(
            "On %s, I recorded %s %s for '%s'. It was processed via my '%s' and categorized as '%s'. Please record this in the double-entry ledger.",
            $date,
            $amount,
            $currency,
            $description,
            $accountName,
            $categoryName,
        );
    }

    private function normalizeName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->normalizeNameFromArray($value);
        }

        if (! is_string($value)) {
            return trim((string) $value) ?: null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $trimmed;
        }

        return $this->normalizeNameFromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function normalizeNameFromArray(array $value): ?string
    {
        return Arr::first([
            $value['en'] ?? null,
            reset($value) ?: null,
        ], fn (mixed $item) => is_string($item) && trim($item) !== '');
    }
}