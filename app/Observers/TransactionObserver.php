<?php

namespace App\Observers;

use App\Domains\Account\Models\Account;
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

        foreach ($this->affectedAccountIds(null, $snapshot) as $accountId) {
            $this->storeAudit(
                transaction: $transaction,
                accountId: $accountId,
                action: TransactionAudit::ACTION_CREATED,
                oldValues: null,
                newValues: $this->snapshotForAuditAccount($snapshot, $accountId),
            );
        }
    }

    public function updating(Transaction $transaction): void
    {
        if (! auth()->id()) {
            return;
        }

        $transaction->storeAuditSnapshot($this->snapshotFromAttributes($transaction, [
            'id' => $transaction->id,
            'account_id' => $transaction->getOriginal('account_id') ?? $transaction->account_id,
            'from_account_id' => $transaction->getOriginal('from_account_id') ?? $transaction->from_account_id,
            'to_account_id' => $transaction->getOriginal('to_account_id') ?? $transaction->to_account_id,
            'amount' => $transaction->getOriginal('amount') ?? $transaction->amount,
            'transaction_type' => $transaction->getOriginal('transaction_type') ?? $transaction->transaction_type,
            'currency' => $transaction->getOriginal('currency') ?? $transaction->currency,
            'note' => $transaction->getOriginal('note') ?? $transaction->note,
            'date' => $transaction->getOriginal('date') ?? $transaction->date,
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
        $accountIds = $this->affectedAccountIds($oldValues, $newValues);

        foreach ($accountIds as $accountId) {
            $this->storeAudit(
                transaction: $transaction,
                accountId: (int) $accountId,
                action: TransactionAudit::ACTION_UPDATED,
                oldValues: $this->snapshotForAuditAccount($oldValues, (int) $accountId),
                newValues: $this->snapshotForAuditAccount($newValues, (int) $accountId),
            );
        }
    }

    public function deleted(Transaction $transaction): void
    {
        if (! auth()->id()) {
            return;
        }

        $snapshot = $this->snapshotFromModel($transaction);

        foreach ($this->affectedAccountIds($snapshot, null) as $accountId) {
            $this->storeAudit(
                transaction: $transaction,
                accountId: $accountId,
                action: TransactionAudit::ACTION_DELETED,
                oldValues: $this->snapshotForAuditAccount($snapshot, $accountId),
                newValues: null,
            );
        }
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
        $transaction->loadMissing(['account', 'fromAccount', 'toAccount']);

        return $this->buildSnapshot(
            attributes: [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'from_account_id' => $transaction->from_account_id,
                'to_account_id' => $transaction->to_account_id,
                'amount' => $transaction->amount,
                'transaction_type' => $transaction->transaction_type,
                'currency' => $transaction->currency,
                'note' => $transaction->note,
                'date' => $transaction->date,
                'created_at' => $transaction->created_at,
            ],
            account: $transaction->account,
            fromAccount: $transaction->fromAccount,
            toAccount: $transaction->toAccount,
        );
    }

    private function snapshotFromAttributes(Transaction $transaction, array $attributes): array
    {
        $accountId = $attributes['account_id'] ?? null;
        $fromAccountId = $attributes['from_account_id'] ?? null;
        $toAccountId = $attributes['to_account_id'] ?? null;

        return $this->buildSnapshot(
            attributes: $attributes,
            account: $accountId ? Account::query()->find($accountId) : null,
            fromAccount: $fromAccountId ? Account::query()->find($fromAccountId) : null,
            toAccount: $toAccountId ? Account::query()->find($toAccountId) : null,
        );
    }

    private function buildSnapshot(array $attributes, ?Account $account, ?Account $fromAccount, ?Account $toAccount): array
    {
        return [
            'id' => isset($attributes['id']) ? (int) $attributes['id'] : null,
            'account_id' => isset($attributes['account_id']) ? (int) $attributes['account_id'] : null,
            'account_name' => $account?->getLocalizedName(),
            'from_account_id' => isset($attributes['from_account_id']) && $attributes['from_account_id'] !== null ? (int) $attributes['from_account_id'] : null,
            'from_account_name' => $fromAccount?->getLocalizedName(),
            'to_account_id' => isset($attributes['to_account_id']) && $attributes['to_account_id'] !== null ? (int) $attributes['to_account_id'] : null,
            'to_account_name' => $toAccount?->getLocalizedName(),
            'amount' => isset($attributes['amount']) ? (float) $attributes['amount'] : null,
            'transaction_type' => $attributes['transaction_type'] ?? null,
            'currency' => $attributes['currency'] ?? null,
            'note' => $attributes['note'] ?? null,
            'date' => $this->normalizeDate($attributes['date'] ?? null),
            'created_at' => $this->normalizeDate($attributes['created_at'] ?? null),
        ];
    }

    private function snapshotForAuditAccount(array $snapshot, int $accountId): array
    {
        if (($snapshot['from_account_id'] ?? null) === $accountId) {
            $snapshot['account_id'] = $accountId;
            $snapshot['account_name'] = $snapshot['from_account_name'] ?? $snapshot['account_name'] ?? null;

            return $snapshot;
        }

        if (($snapshot['to_account_id'] ?? null) === $accountId) {
            $snapshot['account_id'] = $accountId;
            $snapshot['account_name'] = $snapshot['to_account_name'] ?? $snapshot['account_name'] ?? null;

            return $snapshot;
        }

        $snapshot['account_id'] = $accountId;

        return $snapshot;
    }

    /**
     * @return array<int, int>
     */
    private function affectedAccountIds(?array $oldValues, ?array $newValues): array
    {
        $accountIds = collect([
            $oldValues['account_id'] ?? null,
            $oldValues['from_account_id'] ?? null,
            $oldValues['to_account_id'] ?? null,
            $newValues['account_id'] ?? null,
            $newValues['from_account_id'] ?? null,
            $newValues['to_account_id'] ?? null,
        ])->filter()->map(fn(mixed $accountId) => (int) $accountId)->unique()->values();

        $primaryAccountId = $newValues['account_id'] ?? $oldValues['account_id'] ?? null;

        if (! $primaryAccountId) {
            return $accountIds->all();
        }

        return $accountIds
            ->reject(fn(int $accountId) => $accountId === (int) $primaryAccountId)
            ->push((int) $primaryAccountId)
            ->values()
            ->all();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }
}
