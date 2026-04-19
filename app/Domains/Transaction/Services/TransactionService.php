<?php

namespace App\Domains\Transaction\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Category\Services\CategoryService;
use App\Domains\Transaction\Models\Transaction;
use App\Scopes\OwnedAccountScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use RuntimeException;

class TransactionService
{
    private CategoryService $categoryService;

    public function __construct(?CategoryService $categoryService = null)
    {
        $this->categoryService = $categoryService ?? app(CategoryService::class);
    }

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
            ->allowedIncludes(['category', 'account', 'fromAccount', 'toAccount'])
            ->allowedSorts(['id', 'amount', 'created_at'])
            ->defaultSort('-id')
            ->with([
                'category.user:id,name',
                'category.account',
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

    public function createWithOptionalReverse(array $data, int $userId): array
    {
        if (($data['create_reverse_transaction'] ?? false) && ! empty($data['reverse_account_id'])) {
            return [$this->create($this->prepareReverseTransferData($data, $userId))];
        }

        return [$this->create($data)];
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
        if (
            Arr::exists($data, 'from_account_id')
            || Arr::exists($data, 'to_account_id')
            || ($transaction && $transaction->usesDoubleEntry() && ! Arr::exists($data, 'account_id') && ! Arr::exists($data, 'category_id'))
        ) {
            return $this->prepareLedgerTransferData($data, $transaction);
        }

        return $this->prepareCategoryBackedData($data, $transaction);
    }

    private function prepareCategoryBackedData(array $data, ?Transaction $transaction = null): array
    {
        $primaryAccountId = (int) ($data['account_id'] ?? $transaction?->account_id ?? 0);

        if ($primaryAccountId <= 0) {
            throw new RuntimeException('A primary account is required to create a transaction.');
        }

        $primaryAccount = Account::query()->findOrFail($primaryAccountId);
        $category = $this->resolveCompatibilityCategory($data, $transaction, $primaryAccount);

        if (! $category) {
            throw new RuntimeException('A valid category is required to create a category-backed transaction.');
        }

        $counterpartyAccount = $category->account;

        if (! $counterpartyAccount) {
            throw new RuntimeException('The selected category is not linked to a ledger account.');
        }

        $transactionType = Transaction::transactionTypeForCategoryType($category->type);

        $fromAccountId = $transactionType === Transaction::TYPE_CREDIT
            ? (int) $counterpartyAccount->id
            : (int) $primaryAccount->id;
        $toAccountId = $transactionType === Transaction::TYPE_CREDIT
            ? (int) $primaryAccount->id
            : (int) $counterpartyAccount->id;
        $timestamp = $data['date'] ?? $data['created_at'] ?? $transaction?->date ?? $transaction?->created_at ?? now();

        return [
            'user_id' => $primaryAccount->user_id,
            'account_id' => $primaryAccount->id,
            'category_id' => $category->id,
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => (float) ($data['amount'] ?? $transaction?->amount ?? 0),
            'transaction_type' => $transactionType,
            'currency' => strtoupper((string) ($data['currency'] ?? $primaryAccount->currency ?? $transaction?->currency ?? config('hisabi.currency'))),
            'note' => array_key_exists('note', $data) ? $data['note'] : $transaction?->note,
            'description' => array_key_exists('note', $data) ? $data['note'] : $transaction?->description,
            'date' => $timestamp,
            'created_at' => $timestamp,
        ];
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

        $category = $this->resolveCompatibilityCategory($data, $transaction, $toAccount, false);
        $transactionType = $category
            ? Transaction::transactionTypeForCategoryType($category->type)
            : ($fromAccount->type === Account::TYPE_INCOME ? Transaction::TYPE_CREDIT : Transaction::TYPE_DEBIT);
        $primaryAccountId = $transactionType === Transaction::TYPE_CREDIT ? $toAccount->id : $fromAccount->id;
        $timestamp = $data['date'] ?? $data['created_at'] ?? $transaction?->date ?? $transaction?->created_at ?? now();

        return [
            'user_id' => $fromAccount->user_id,
            'account_id' => $primaryAccountId,
            'category_id' => $category?->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => (float) ($data['amount'] ?? $transaction?->amount ?? 0),
            'transaction_type' => $transactionType,
            'currency' => strtoupper((string) ($data['currency'] ?? $fromAccount->currency ?? $transaction?->currency ?? config('hisabi.currency'))),
            'note' => array_key_exists('note', $data) ? $data['note'] : $transaction?->note,
            'description' => array_key_exists('note', $data) ? $data['note'] : $transaction?->description,
            'date' => $timestamp,
            'created_at' => $timestamp,
        ];
    }

    private function prepareReverseTransferData(array $data, int $userId): array
    {
        return [
            'from_account_id' => (int) $data['reverse_account_id'],
            'to_account_id' => (int) $data['account_id'],
            'account_id' => (int) $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'amount' => $data['amount'],
            'transaction_type' => Transaction::TYPE_CREDIT,
            'currency' => $data['currency'] ?? null,
            'note' => $data['note'] ?? null,
            'created_at' => $data['created_at'],
        ];
    }

    private function resolveCompatibilityCategory(
        array $data,
        ?Transaction $transaction,
        Account $account,
        bool $allowFallbackType = true
    ): ?Category {
        $categoryId = $data['category_id'] ?? $transaction?->category_id;

        if ($categoryId) {
            return $this->categoryService->findLedgerCategoryOrFail((int) $categoryId);
        }

        $categoryType = $data['category_type'] ?? null;

        if (! $categoryType && ! $allowFallbackType) {
            return null;
        }

        if (! $categoryType && $transaction?->category) {
            return $this->categoryService->findLedgerCategoryOrFail((int) $transaction->category->id);
        }

        if (! $categoryType) {
            return null;
        }

        $legacyCategory = Category::findOrCreateFallbackForUser((int) $account->user_id, strtoupper((string) $categoryType));

        return $this->categoryService->findLedgerCategoryOrFail((int) $legacyCategory->id);
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
            ->forAccessibleAccounts(Auth::user());
    }
}
