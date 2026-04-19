<?php

namespace App\Domains\Category\Services;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CategoryService
{
    public function getAll(): Collection
    {
        $userId = Auth::id();

        if ($userId) {
            $this->syncLedgerCategoriesForOwners([(int) $userId]);
        }

        return QueryBuilder::for(Category::query()->withoutGlobalScope(TenantScope::class)->whereNotNull('account_id'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('name', 'LIKE', "%$value%");
                }),
                AllowedFilter::exact('type'),
            ])
            ->allowedSorts(['id', 'name', 'type'])
            ->defaultSort('-id')
            ->with('account')
            ->withCount('transactions')
            ->get();
    }

    public function create(array $data): Category
    {
        $userId = (int) ($data['user_id'] ?? Auth::id());
        $legacyType = strtoupper((string) $data['type']);
        $user = User::query()->findOrFail($userId);

        $account = Account::query()->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $this->accountTypeForCategoryType($legacyType),
            'balance' => 0,
            'currency' => strtoupper((string) ($data['currency'] ?? $user->default_currency ?: config('hisabi.currency'))),
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return $this->upsertCompatibilityCategory($account, $legacyType, null, $data)
            ->load('account');
    }

    public function update(int $id, array $data): Category
    {
        $category = $this->findLedgerCategoryOrFail($id);
        $account = $category->account;

        if (! $account) {
            $account = $this->findOrCreateAccountForCategory($category);
        }

        $legacyType = strtoupper((string) ($data['type'] ?? $category->type));

        $account->forceFill([
            'name' => $data['name'] ?? $account->name,
            'type' => $this->accountTypeForCategoryType($legacyType),
            'color' => Arr::exists($data, 'color') ? $data['color'] : $account->color,
            'icon' => Arr::exists($data, 'icon') ? $data['icon'] : $account->icon,
        ]);

        if ($account->trashed()) {
            $account->restore();
        }

        $account->save();

        return $this->upsertCompatibilityCategory($account, $legacyType, $category, $data)
            ->load('account');
    }

    public function delete(int $id): Category
    {
        $category = $this->findLedgerCategoryOrFail($id);

        if ($category->account && ! $category->account->trashed()) {
            $category->account->delete();
        }

        $category->delete();

        return $category;
    }

    /**
     * @param  array<int, int>  $ownerIds
     */
    public function syncLedgerCategoriesForOwners(array $ownerIds): void
    {
        $normalizedOwnerIds = array_values(array_unique(array_filter(array_map('intval', $ownerIds))));

        if ($normalizedOwnerIds === []) {
            return;
        }

        $accounts = Account::query()
            ->withTrashed()
            ->whereIn('user_id', $normalizedOwnerIds)
            ->get();

        foreach ($accounts as $account) {
            $legacyType = $this->legacyTypeForAccount($account);

            if (! $legacyType) {
                continue;
            }

            $this->upsertCompatibilityCategory($account, $legacyType);
        }

        $legacyCategories = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('user_id', $normalizedOwnerIds)
            ->whereNull('account_id')
            ->get();

        foreach ($legacyCategories as $legacyCategory) {
            $account = $this->findOrCreateAccountForCategory($legacyCategory);

            $this->upsertCompatibilityCategory($account, $legacyCategory->type, $legacyCategory);
        }
    }

    public function findLedgerCategoryOrFail(int $id): Category
    {
        $category = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('account')
            ->findOrFail($id);

        if ($category->account_id) {
            if ($category->account && $category->account->trashed()) {
                $category->account->restore();
            }

            return $category->fresh(['account']);
        }

        $account = $this->findOrCreateAccountForCategory($category);

        return $this->upsertCompatibilityCategory($account, $category->type, $category)
            ->load('account');
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    public function accountIdsForCategoryIds(array $categoryIds): array
    {
        return collect($categoryIds)
            ->map(fn(mixed $categoryId) => $this->findLedgerCategoryOrFail((int) $categoryId))
            ->pluck('account_id')
            ->map(fn(mixed $accountId) => (int) $accountId)
            ->unique()
            ->values()
            ->all();
    }

    public function accountTypeForCategoryType(string $categoryType): string
    {
        switch (strtoupper($categoryType)) {
            case Category::INCOME:
                return Account::TYPE_INCOME;

            case Category::EXPENSES:
                return Account::TYPE_EXPENSE;

            case Category::SAVINGS:
            case Category::INVESTMENT:
            default:
                return Account::TYPE_ASSET;
        }
    }

    public function legacyTypeForAccount(Account $account): ?string
    {
        $existingCategory = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->where('account_id', $account->id)
            ->first();

        if ($existingCategory) {
            return $existingCategory->type;
        }

        switch ($account->type) {
            case Account::TYPE_INCOME:
                return Category::INCOME;

            case Account::TYPE_EXPENSE:
                return Category::EXPENSES;

            case Account::TYPE_ASSET:
                $legacyCategory = $this->matchingLegacyCategoryForAccount($account);

                return $legacyCategory ? $legacyCategory->type : null;

            default:
                return null;
        }
    }

    public function compatibilityCategoryForAccount(Account $account, ?string $legacyType = null): Category
    {
        $resolvedLegacyType = $legacyType ?? $this->legacyTypeForAccount($account);

        if (! $resolvedLegacyType) {
            throw new \RuntimeException('The specified account does not have a category-compatible ledger type.');
        }

        return $this->upsertCompatibilityCategory($account, $resolvedLegacyType)->load('account');
    }

    private function upsertCompatibilityCategory(Account $account, string $legacyType, ?Category $seedCategory = null, array $data = []): Category
    {
        $translations = $data['name'] ?? $seedCategory?->getTranslations('name') ?? $account->getTranslations('name');
        $color = Arr::exists($data, 'color') ? $data['color'] : ($seedCategory?->color ?? $account->color ?? 'gray');
        $icon = Arr::exists($data, 'icon') ? $data['icon'] : ($seedCategory?->icon ?? $account->icon ?? 'shapes');

        $category = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->where('account_id', $account->id)
            ->first();

        if (! $category) {
            $category = $seedCategory;
        }

        if (! $category) {
            $category = $this->matchingLegacyCategoryForAccount($account);
        }

        $payload = [
            'account_id' => $account->id,
            'user_id' => $account->user_id,
            'name' => $translations,
            'type' => strtoupper($legacyType),
            'color' => $color,
            'icon' => $icon,
        ];

        if ($category) {
            $category->forceFill($payload);

            if ($category->trashed()) {
                $category->restore();
            }

            $category->save();

            return $category;
        }

        return Category::query()->create($payload);
    }

    private function findOrCreateAccountForCategory(Category $category): Account
    {
        $accountType = $this->accountTypeForCategoryType($category->type);
        $normalizedName = $this->normalizedEnglishName($category->getTranslations('name'));

        $account = Account::query()
            ->withTrashed()
            ->where('user_id', $category->user_id)
            ->where('type', $accountType)
            ->get()
            ->first(function (Account $candidate) use ($normalizedName) {
                return $this->normalizedEnglishName($candidate->getTranslations('name')) === $normalizedName;
            });

        if ($account) {
            if ($account->trashed()) {
                $account->restore();
            }

            $account->forceFill([
                'name' => $category->getTranslations('name'),
                'color' => $category->color,
                'icon' => $category->icon,
            ])->save();

            return $account;
        }

        $user = User::query()->find($category->user_id);

        return Account::query()->create([
            'user_id' => $category->user_id,
            'name' => $category->getTranslations('name'),
            'type' => $accountType,
            'balance' => 0,
            'currency' => strtoupper((string) (($user?->default_currency) ?: config('hisabi.currency'))),
            'color' => $category->color,
            'icon' => $category->icon,
        ]);
    }

    private function matchingLegacyCategoryForAccount(Account $account): ?Category
    {
        $normalizedName = $this->normalizedEnglishName($account->getTranslations('name'));

        return Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNull('account_id')
            ->where('user_id', $account->user_id)
            ->get()
            ->first(function (Category $category) use ($normalizedName) {
                return $this->normalizedEnglishName($category->getTranslations('name')) === $normalizedName;
            });
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function normalizedEnglishName(array $translations): string
    {
        $value = $translations['en'] ?? Arr::first($translations);

        return mb_strtolower(trim((string) $value));
    }
}
