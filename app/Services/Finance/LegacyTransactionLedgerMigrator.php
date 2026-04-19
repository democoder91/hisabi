<?php

namespace App\Services\Finance;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyTransactionLedgerMigrator
{
    public function migrate(User $user, object $legacyTransaction): Transaction
    {
        return DB::transaction(function () use ($user, $legacyTransaction) {
            $categoryType = $this->normalizeCategoryType(
                $legacyTransaction->category_type ?? null,
                $legacyTransaction->transaction_type ?? null,
            );
            $transactionType = $categoryType === Category::INCOME
                ? Transaction::TYPE_CREDIT
                : Transaction::TYPE_DEBIT;

            $legacyAssetAccount = $this->findOrCreateAccount(
                $user,
                $this->translatedName($legacyTransaction->account_name ?? null, Account::DEFAULT_NAME),
                Account::TYPE_ASSET,
                (string) (($legacyTransaction->account_currency ?? null) ?: ($legacyTransaction->currency ?? null) ?: $user->default_currency ?: config('hisabi.currency')),
                $legacyTransaction->account_color ?? null,
                $legacyTransaction->account_icon ?? null,
            );

            $counterpartyAccount = $this->findOrCreateAccount(
                $user,
                $this->translatedName($legacyTransaction->category_name ?? null, $this->fallbackLedgerAccountName($categoryType)),
                $this->ledgerAccountTypeForCategory($categoryType),
                (string) (($legacyTransaction->currency ?? null) ?: ($legacyTransaction->account_currency ?? null) ?: $user->default_currency ?: config('hisabi.currency')),
                $legacyTransaction->category_color ?? 'gray',
                $legacyTransaction->category_icon ?? 'shapes',
            );

            $fromAccount = $transactionType === Transaction::TYPE_CREDIT
                ? $counterpartyAccount
                : $legacyAssetAccount;
            $toAccount = $transactionType === Transaction::TYPE_CREDIT
                ? $legacyAssetAccount
                : $counterpartyAccount;

            $liveCategoryId = $this->resolveLiveCategoryId($user, $legacyTransaction, $categoryType);
            $description = trim((string) ($legacyTransaction->description ?? $legacyTransaction->note ?? 'Migrated legacy transaction'));
            $timestamp = Carbon::parse($legacyTransaction->date ?? $legacyTransaction->created_at ?? now());

            return Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $transactionType === Transaction::TYPE_CREDIT ? $toAccount->id : $fromAccount->id,
                'category_id' => $liveCategoryId,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => round((float) ($legacyTransaction->amount ?? 0), 2),
                'currency' => strtoupper((string) (($legacyTransaction->currency ?? null) ?: ($legacyTransaction->account_currency ?? null) ?: $user->default_currency ?: config('hisabi.currency'))),
                'transaction_type' => $transactionType,
                'note' => $description,
                'description' => $description,
                'date' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => Carbon::parse($legacyTransaction->legacy_updated_at ?? $legacyTransaction->date ?? now()),
            ]);
        });
    }

    private function normalizeCategoryType(?string $legacyCategoryType, ?string $legacyTransactionType): string
    {
        $normalizedCategoryType = strtoupper(trim((string) $legacyCategoryType));

        if ($normalizedCategoryType !== '') {
            return $normalizedCategoryType;
        }

        return strtoupper(trim((string) $legacyTransactionType)) === Transaction::TYPE_CREDIT
            ? Category::INCOME
            : Category::EXPENSES;
    }

    private function ledgerAccountTypeForCategory(string $categoryType): string
    {
        switch ($categoryType) {
            case Category::INCOME:
                return Account::TYPE_INCOME;

            case Category::EXPENSES:
                return Account::TYPE_EXPENSE;

            default:
                return Account::TYPE_ASSET;
        }
    }

    private function fallbackLedgerAccountName(string $categoryType): string
    {
        switch ($categoryType) {
            case Category::INCOME:
                return 'Uncategorized Income';

            case Category::SAVINGS:
                return 'Uncategorized Savings';

            case Category::INVESTMENT:
                return 'Uncategorized Investment';

            default:
                return 'Uncategorized Expenses';
        }
    }

    private function resolveLiveCategoryId(User $user, object $legacyTransaction, string $categoryType): int
    {
        $legacyCategoryId = (int) ($legacyTransaction->legacy_category_id ?? 0);

        if ($legacyCategoryId > 0 && Category::query()->withoutGlobalScopes()->whereKey($legacyCategoryId)->exists()) {
            return $legacyCategoryId;
        }

        return Category::findOrCreateFallbackForUser($user->id, $categoryType)->id;
    }

    /**
     * @param  array<string, string|null>  $translations
     */
    private function findOrCreateAccount(
        User $user,
        array $translations,
        string $type,
        string $currency,
        ?string $color,
        ?string $icon
    ): Account {
        $englishName = mb_strtolower(trim((string) ($translations['en'] ?? '')));

        $existingAccount = Account::query()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->get()
            ->first(function (Account $account) use ($englishName) {
                return mb_strtolower($this->localizedEnglishName($account->name)) === $englishName;
            });

        if ($existingAccount) {
            $existingAccount->forceFill([
                'currency' => strtoupper($currency),
                'color' => $existingAccount->color ?: $color,
                'icon' => $existingAccount->icon ?: $icon,
                'type' => $type,
            ]);

            if ($existingAccount->trashed()) {
                $existingAccount->restore();
            }

            $existingAccount->saveQuietly();

            return $existingAccount;
        }

        return Account::query()->create([
            'user_id' => $user->id,
            'name' => $translations,
            'type' => $type,
            'balance' => 0,
            'currency' => strtoupper($currency),
            'color' => $color,
            'icon' => $icon,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function translatedName(mixed $value, string $fallback): array
    {
        if (is_array($value)) {
            return [
                'en' => $this->firstNonEmptyString($value) ?: $fallback,
                'ar' => isset($value['ar']) && is_string($value['ar']) && trim($value['ar']) !== '' ? trim($value['ar']) : null,
            ];
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $this->translatedName($decoded, $fallback);
                }

                return ['en' => $trimmed, 'ar' => null];
            }
        }

        return ['en' => $fallback, 'ar' => null];
    }

    /**
     * @param  array<int|string, mixed>|string  $name
     */
    private function localizedEnglishName($name): string
    {
        if (is_array($name)) {
            return $this->firstNonEmptyString($name) ?: '';
        }

        $decoded = json_decode($name, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->firstNonEmptyString($decoded) ?: '';
        }

        return trim($name);
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach (['en', 'ar'] as $preferredKey) {
            $preferredValue = $values[$preferredKey] ?? null;

            if (is_string($preferredValue) && trim($preferredValue) !== '') {
                return trim($preferredValue);
            }
        }

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
