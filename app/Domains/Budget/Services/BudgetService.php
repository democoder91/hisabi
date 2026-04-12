<?php

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\Budget;
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
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $budget->categories()->sync($data['category_ids']);

        return $budget->load('categories');
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'start_at' => $data['start_at'],
            'end_at' => $data['reoccurrence'] === Budget::CUSTOM ? $data['end_at'] : ($data['end_at'] ?? null),
            'saving' => $data['saving'] ?? false,
            'period' => $data['period'],
            'reoccurrence' => $data['reoccurrence'],
        ]);

        $budget->categories()->sync($data['category_ids']);

        return $budget->load('categories');
    }

    public function delete(Budget $budget): Budget
    {
        $budget->loadMissing('categories');
        $budget->delete();

        return $budget;
    }
}
