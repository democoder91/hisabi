<?php

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\Budget;
use App\Domains\Budget\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;

class BudgetService
{
    public function getAll(): Collection
    {
        return QueryBuilder::for(Budget::class)
            ->with('categories')
            ->allowedSorts(['id', 'amount', 'start_at'])
            ->get();
    }

    public function findOwnedOrFail(int $id): Budget
    {
        return Budget::with('categories')->findOrFail($id);
    }

    public function create(array $data): Budget
    {
        $budget = Budget::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncCategories($budget, $data['category_ids']);

        return $budget->load('categories');
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $this->syncCategories($budget, $data['category_ids']);

        return $budget->load('categories');
    }

    public function delete(Budget $budget): Budget
    {
        $budget->loadMissing('categories');
        $budget->delete();

        return $budget;
    }

    private function syncCategories(Budget $budget, array $categoryIds): void
    {
        $categoryIds = collect($categoryIds)
            ->map(fn ($categoryId) => (int) $categoryId)
            ->unique()
            ->values();

        $detachQuery = BudgetCategory::query()->where('budget_id', $budget->id);

        if ($categoryIds->isNotEmpty()) {
            $detachQuery->whereNotIn('category_id', $categoryIds->all());
        }

        $detachQuery->delete();

        $existingLinks = BudgetCategory::withTrashed()
            ->where('budget_id', $budget->id)
            ->whereIn('category_id', $categoryIds->all())
            ->get()
            ->keyBy('category_id');

        foreach ($categoryIds as $categoryId) {
            $existingLink = $existingLinks->get($categoryId);

            if ($existingLink) {
                if ($existingLink->trashed()) {
                    $existingLink->restore();
                }

                continue;
            }

            BudgetCategory::create([
                'budget_id' => $budget->id,
                'category_id' => $categoryId,
            ]);
        }
    }
}
