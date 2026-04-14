<?php

namespace App\Domains\Transaction\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Scopes\OwnedAccountScope;
use App\Scopes\TenantScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class TransactionService
{
    public function getPaginated(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for($this->accessibleQuery())
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('amount', 'LIKE', "%$value%")
                            ->orWhere('note', 'LIKE', "%$value%")
                            ->orWhereHas('category', function ($builder) use ($value) {
                                $builder->where('name', 'LIKE', "%$value%");
                            });
                    });
                }),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                }),
            ])
            ->allowedIncludes(['category', 'account'])
            ->allowedSorts(['id', 'amount', 'created_at'])
            ->defaultSort('-id')
            ->with(['category.user:id,name', 'account.user:id,name', 'account.sharedUsers:id,name,email'])
            ->paginate($perPage);
    }

    public function create(array $data): Transaction
    {
        return Transaction::query()->create($this->prepareData($data));
    }

    public function createWithOptionalReverse(array $data, int $userId): array
    {
        $transactions = [$this->create($data)];

        if (($data['create_reverse_transaction'] ?? false) && ! empty($data['reverse_account_id'])) {
            $transactions[] = $this->create($this->prepareReverseData($data, $userId));
        }

        return $transactions;
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
        $category = null;

        if (! empty($data['category_id'])) {
            $category = Category::query()
                ->withoutGlobalScope(TenantScope::class)
                ->find($data['category_id']);
        }

        if (! empty($data['account_id'])) {
            Account::query()->findOrFail($data['account_id']);
        }

        $data['transaction_type'] = $category?->type
            ? Transaction::transactionTypeForCategoryType($category->type)
            : strtoupper($data['transaction_type'] ?? $transaction?->transaction_type ?? Transaction::TYPE_DEBIT);

        return Arr::only($data, [
            'account_id',
            'category_id',
            'amount',
            'transaction_type',
            'currency',
            'note',
            'created_at',
        ]);
    }

    private function prepareReverseData(array $data, int $userId): array
    {
        $reverseAccount = Account::query()->findOrFail((int) $data['reverse_account_id']);
        $reverseCategory = $this->fallbackCategoryForAccount($reverseAccount, $userId, Category::EXPENSES);

        return [
            'account_id' => $reverseAccount->id,
            'category_id' => $reverseCategory->id,
            'amount' => $data['amount'],
            'transaction_type' => Transaction::TYPE_DEBIT,
            'note' => $data['note'] ?? null,
            'created_at' => $data['created_at'],
        ];
    }

    private function fallbackCategoryForAccount(Account $account, int $userId, string $type): Category
    {
        $fallbackOwnerId = in_array($userId, $account->participantUserIds(), true)
            ? $userId
            : (int) $account->user_id;

        return Category::findOrCreateFallbackForUser($fallbackOwnerId, $type);
    }

    private function accessibleQuery(): Builder
    {
        return Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts(auth()->user());
    }
}
