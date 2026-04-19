<?php

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\Budget;
use App\Domains\Budget\Models\BudgetAccount;
use App\Domains\Budget\Models\BudgetCategory;
use App\Domains\Category\Services\CategoryService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;

class BudgetService
{
    private CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function getAll(): Collection
    {
        return QueryBuilder::for(Budget::class)
            ->with(['categories', 'accounts.compatibilityCategory'])
            ->allowedSorts(['id', 'amount', 'start_at'])
            ->get();
    }

    public function findOwnedOrFail(int $id): Budget
    {
        return Budget::with(['categories', 'accounts.compatibilityCategory'])->findOrFail($id);
    }

    public function create(array $data): Budget
    {
        $budget = Budget::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $this->resolveCurrency($data['currency'] ?? null),
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncCategories($budget, $data['category_ids']);

        return $budget->load(['categories', 'accounts.compatibilityCategory']);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $this->resolveCurrency($data['currency'] ?? $budget->currency),
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncCategories($budget, $data['category_ids']);

        return $budget->load(['categories', 'accounts.compatibilityCategory']);
    }

    public function delete(Budget $budget): Budget
    {
        $budget->loadMissing(['categories', 'accounts.compatibilityCategory']);
        $budget->delete();

        return $budget;
    }

    private function syncCategories(Budget $budget, array $categoryIds): void
    {
        $normalizedCategoryIds = collect($categoryIds)
            ->map(fn ($categoryId) => (int) $categoryId)
            ->unique()
            ->values();
        $accountIds = collect($this->categoryService->accountIdsForCategoryIds($categoryIds))
            ->map(fn ($accountId) => (int) $accountId)
            ->unique()
            ->values();

        $categoryDetachQuery = BudgetCategory::query()->where('budget_id', $budget->id);

        if ($normalizedCategoryIds->isNotEmpty()) {
            $categoryDetachQuery->whereNotIn('category_id', $normalizedCategoryIds->all());
        }

        $categoryDetachQuery->delete();

        $existingCategoryLinks = BudgetCategory::withTrashed()
            ->where('budget_id', $budget->id)
            ->whereIn('category_id', $normalizedCategoryIds->all())
            ->get()
            ->keyBy('category_id');

        foreach ($normalizedCategoryIds as $categoryId) {
            $existingCategoryLink = $existingCategoryLinks->get($categoryId);

            if ($existingCategoryLink) {
                if ($existingCategoryLink->trashed()) {
                    $existingCategoryLink->restore();
                }

                continue;
            }

            BudgetCategory::create([
                'budget_id' => $budget->id,
                'category_id' => $categoryId,
            ]);
        }

        $detachQuery = BudgetAccount::query()->where('budget_id', $budget->id);

        if ($accountIds->isNotEmpty()) {
            $detachQuery->whereNotIn('account_id', $accountIds->all());
        }

        $detachQuery->delete();

        $existingLinks = BudgetAccount::withTrashed()
            ->where('budget_id', $budget->id)
            ->whereIn('account_id', $accountIds->all())
            ->get()
            ->keyBy('account_id');

        foreach ($accountIds as $accountId) {
            $existingLink = $existingLinks->get($accountId);

            if ($existingLink) {
                if ($existingLink->trashed()) {
                    $existingLink->restore();
                }

                continue;
            }

            BudgetAccount::create([
                'budget_id' => $budget->id,
                'account_id' => $accountId,
            ]);
        }
    }

    private function resolveCurrency(?string $currency): string
    {
        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        $user = Auth::user();

        if ($user instanceof User && $user->default_currency) {
            return strtoupper($user->default_currency);
        }

        return strtoupper((string) config('hisabi.currency', 'EGP'));
    }
}
