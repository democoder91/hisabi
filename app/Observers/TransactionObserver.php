<?php

namespace App\Observers;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Models\TransactionAudit;
use Illuminate\Support\Carbon;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if (! auth()->id()) {
            return;
        }

        $snapshot = $this->snapshotFromModel($transaction);

        $this->storeAudit(
            transaction: $transaction,
            accountId: (int) $transaction->account_id,
            action: TransactionAudit::ACTION_CREATED,
            oldValues: null,
            newValues: $snapshot,
        );
    }

    public function updating(Transaction $transaction): void
    {
        if (! auth()->id()) {
            return;
        }

        $transaction->storeAuditSnapshot($this->snapshotFromAttributes($transaction, [
            'id' => $transaction->id,
            'account_id' => $transaction->getOriginal('account_id') ?? $transaction->account_id,
            'category_id' => $transaction->getOriginal('category_id') ?? $transaction->category_id,
            'amount' => $transaction->getOriginal('amount') ?? $transaction->amount,
            'transaction_type' => $transaction->getOriginal('transaction_type') ?? $transaction->transaction_type,
            'currency' => $transaction->getOriginal('currency') ?? $transaction->currency,
            'note' => $transaction->getOriginal('note') ?? $transaction->note,
            'created_at' => $transaction->getOriginal('created_at') ?? $transaction->created_at,
        ]));
    }

    public function updated(Transaction $transaction): void
    {
        if (! auth()->id()) {
            $transaction->pullAuditSnapshot();

            return;
        }

        $oldValues = $transaction->pullAuditSnapshot();

        if ($oldValues === []) {
            return;
        }

        $newValues = $this->snapshotFromModel($transaction);
        $accountIds = collect([
            $oldValues['account_id'] ?? null,
            $newValues['account_id'] ?? null,
        ])->filter()->unique()->values();

        foreach ($accountIds as $accountId) {
            $this->storeAudit(
                transaction: $transaction,
                accountId: (int) $accountId,
                action: TransactionAudit::ACTION_UPDATED,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }
    }

    public function deleted(Transaction $transaction): void
    {
        if (! auth()->id()) {
            return;
        }

        $this->storeAudit(
            transaction: $transaction,
            accountId: (int) $transaction->account_id,
            action: TransactionAudit::ACTION_DELETED,
            oldValues: $this->snapshotFromModel($transaction),
            newValues: null,
        );
    }

    private function storeAudit(
        Transaction $transaction,
        int $accountId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        TransactionAudit::query()->create([
            'transaction_id' => $transaction->id,
            'account_id' => $accountId,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    private function snapshotFromModel(Transaction $transaction): array
    {
        $transaction->loadMissing(['account', 'category']);

        return $this->buildSnapshot(
            attributes: [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'amount' => $transaction->amount,
                'transaction_type' => $transaction->transaction_type,
                'currency' => $transaction->currency,
                'note' => $transaction->note,
                'created_at' => $transaction->created_at,
            ],
            account: $transaction->account,
            category: $transaction->category,
        );
    }

    private function snapshotFromAttributes(Transaction $transaction, array $attributes): array
    {
        $accountId = $attributes['account_id'] ?? null;
        $categoryId = $attributes['category_id'] ?? null;

        return $this->buildSnapshot(
            attributes: $attributes,
            account: $accountId ? Account::query()->find($accountId) : null,
            category: $categoryId ? Category::withoutGlobalScopes()->find($categoryId) : null,
        );
    }

    private function buildSnapshot(array $attributes, ?Account $account, ?Category $category): array
    {
        return [
            'id' => isset($attributes['id']) ? (int) $attributes['id'] : null,
            'account_id' => isset($attributes['account_id']) ? (int) $attributes['account_id'] : null,
            'account_name' => $account?->getLocalizedName(),
            'category_id' => isset($attributes['category_id']) && $attributes['category_id'] !== null ? (int) $attributes['category_id'] : null,
            'category_name' => $category?->getTranslation('name', app()->getLocale(), false) ?: $category?->getTranslation('name', 'en', false),
            'amount' => isset($attributes['amount']) ? (float) $attributes['amount'] : null,
            'transaction_type' => $attributes['transaction_type'] ?? null,
            'currency' => $attributes['currency'] ?? null,
            'note' => $attributes['note'] ?? null,
            'created_at' => $this->normalizeDate($attributes['created_at'] ?? null),
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }
}