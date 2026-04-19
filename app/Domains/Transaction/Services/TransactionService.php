<?php

namespace App\Domains\Transaction\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Scopes\OwnedAccountScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use RuntimeException;

class TransactionService
{
    public function getPaginated(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for($this->accessibleQuery())
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $search = trim((string) $value);
                    $like = "%{$search}%";
                    $locale = Auth::user()?->locale ?? app()->getLocale();
                    $nameExpression = Account::localizedNameSqlExpression($locale);

                    $query->where(function ($builder) use ($like, $nameExpression) {
                        $builder->where('amount', 'LIKE', $like)
                            ->orWhere('note', 'LIKE', $like)
                            ->orWhereHas('account', function ($accountQuery) use ($nameExpression, $like) {
                                $accountQuery->whereRaw("{$nameExpression} LIKE ?", [$like]);
                            })
                            ->orWhereHas('fromAccount', function ($accountQuery) use ($nameExpression, $like) {
                                $accountQuery->whereRaw("{$nameExpression} LIKE ?", [$like]);
                            })
                            ->orWhereHas('toAccount', function ($accountQuery) use ($nameExpression, $like) {
                                $accountQuery->whereRaw("{$nameExpression} LIKE ?", [$like]);
                            });
                    });
                }),
                AllowedFilter::callback('account_id', function ($query, $value) {
                    $query->where(function ($accountQuery) use ($value) {
                        $accountQuery->where('account_id', $value)
                            ->orWhere('from_account_id', $value)
                            ->orWhere('to_account_id', $value);
                    });
                }),
                AllowedFilter::exact('from_account_id'),
                AllowedFilter::exact('to_account_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                }),
            ])
            ->allowedIncludes(['account', 'fromAccount', 'toAccount'])
            ->allowedSorts(['id', 'amount', 'created_at'])
            ->defaultSort('-id')
            ->with([
                'account.user:id,name',
                'account.sharedUsers:id,name,email',
                'fromAccount.user:id,name',
                'toAccount.user:id,name',
            ])
            ->paginate($perPage);
    }

    public function create(array $data): Transaction
    {
        return Transaction::query()->create($this->prepareData($data));
    }

    public function createTransfer(array $data): Transaction
    {
        return $this->create([
            ...$data,
            'from_account_id' => $data['from_account_id'] ?? null,
            'to_account_id' => $data['to_account_id'] ?? null,
        ]);
    }

    public function update(int $id, array $data): Transaction
    {
        $transaction = Transaction::query()->withoutGlobalScope(OwnedAccountScope::class)->findOrFail($id);
        $transaction->update($this->prepareData($data, $transaction));

        return $transaction->fresh();
    }

    public function delete(int $id): Transaction
    {
        $transaction = Transaction::query()->withoutGlobalScope(OwnedAccountScope::class)->findOrFail($id);
        $transaction->delete();

        return $transaction;
    }

    private function prepareData(array $data, ?Transaction $transaction = null): array
    {
        return $this->prepareLedgerTransferData($data, $transaction);
    }

    private function prepareLedgerTransferData(array $data, ?Transaction $transaction = null): array
    {
        $fromAccountId = (int) ($data['from_account_id'] ?? $transaction?->from_account_id ?? 0);
        $toAccountId = (int) ($data['to_account_id'] ?? $transaction?->to_account_id ?? 0);

        if ($fromAccountId <= 0 || $toAccountId <= 0) {
            throw new RuntimeException('Both from_account_id and to_account_id are required for ledger transfers.');
        }

        $fromAccount = Account::query()->findOrFail($fromAccountId);
        $toAccount = Account::query()->findOrFail($toAccountId);

        if ((int) $fromAccount->id === (int) $toAccount->id) {
            throw new RuntimeException('The source and destination accounts must be different.');
        }

        $transactionType = $fromAccount->type === Account::TYPE_INCOME
            ? Transaction::TYPE_CREDIT
            : Transaction::TYPE_DEBIT;
        $primaryAccountId = $transactionType === Transaction::TYPE_CREDIT ? $toAccount->id : $fromAccount->id;
        $timestamp = $data['date'] ?? $data['created_at'] ?? $transaction?->date ?? $transaction?->created_at ?? now();

        return [
            'user_id' => $fromAccount->user_id,
            'account_id' => $primaryAccountId,
            'category_id' => null,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => (float) ($data['amount'] ?? $transaction?->amount ?? 0),
            'transaction_type' => $transactionType,
            'currency' => strtoupper((string) ($fromAccount->currency ?? $transaction?->currency ?? config('hisabi.currency'))),
            'note' => array_key_exists('note', $data) ? $data['note'] : $transaction?->note,
            'description' => array_key_exists('note', $data) ? $data['note'] : $transaction?->description,
            'date' => $timestamp,
            'created_at' => $timestamp,
        ];
    }

    private function accessibleQuery(): Builder
    {
        return Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts(Auth::user());
    }
}
