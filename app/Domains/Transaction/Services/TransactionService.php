<?php

namespace App\Domains\Transaction\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class TransactionService
{
    public function getPaginated(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for($this->accessibleQuery())
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function($q) use ($value) {
                        $q->where('amount', 'LIKE', "%$value%")
                            ->orWhere('note', 'LIKE', "%$value%")
                            ->orWhereHas('brand', function($builder) use($value) {
                                $builder->where('name', 'LIKE', "%$value%");
                            });
                    });
                }),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::callback('category_id', function ($query, $value) {
                    $query->whereHas('brand', function($builder) use($value) {
                        $builder->where('category_id', $value);
                    });
                }),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                }),
            ])
                ->allowedIncludes(['brand.category', 'account'])
            ->allowedSorts(['id', 'amount', 'created_at'])
            ->defaultSort('-id')
                ->with(['brand.category', 'account'])
            ->paginate($perPage);
    }

    public function create(array $data): Transaction
    {
        return Transaction::query()->create($this->prepareData($data));
    }

    public function update(int $id, array $data): Transaction
    {
        $transaction = $this->accessibleQuery()->findOrFail($id);
        $transaction->update($this->prepareData($data, $transaction));
        return $transaction->fresh();
    }

    public function delete(int $id): Transaction
    {
        $transaction = $this->accessibleQuery()->findOrFail($id);
        $transaction->delete();
        return $transaction;
    }

    private function prepareData(array $data, ?Transaction $transaction = null): array
    {
        $brand = null;

        if (! empty($data['brand_id'])) {
            $brand = Brand::withoutGlobalScopes()->with('category')->find($data['brand_id']);
        }

        if (! empty($data['account_id'])) {
            Account::query()->findOrFail($data['account_id']);
        }

        $data['transaction_type'] = $brand?->category?->type
            ? Transaction::transactionTypeForCategoryType($brand->category->type)
            : strtoupper($data['transaction_type'] ?? $transaction?->transaction_type ?? Transaction::TYPE_DEBIT);

        return $data;
    }

    private function accessibleQuery(): Builder
    {
        return Transaction::query()
            ->withoutGlobalScopes()
            ->forAccessibleAccounts(auth()->user());
    }
}

