<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CategoryStatsMetric extends Metric
{
    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('categories.name');

        $query = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        $mostUsedCategory = (clone $query)
            ->selectRaw("categories.id, {$labelExpression} as name, COUNT(transactions.id) as transaction_count")
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('transaction_count', 'DESC')
            ->first();

        $highestSpendingCategory = (clone $query)
            ->selectRaw("categories.id, {$labelExpression} as name, SUM(transactions.amount) as total_amount")
            ->where('categories.type', Category::EXPENSES)
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('total_amount', 'DESC')
            ->first();

        $highestIncomeCategory = (clone $query)
            ->selectRaw("categories.id, {$labelExpression} as name, SUM(transactions.amount) as total_amount")
            ->where('categories.type', Category::INCOME)
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('total_amount', 'DESC')
            ->first();

        return [
            'mostUsedCategory' => $mostUsedCategory ? [
                'name' => $mostUsedCategory->name,
                'count' => $mostUsedCategory->transaction_count
            ] : null,
            'highestSpendingCategory' => $highestSpendingCategory ? [
                'name' => $highestSpendingCategory->name,
                'amount' => $highestSpendingCategory->total_amount
            ] : null,
            'highestIncomeCategory' => $highestIncomeCategory ? [
                'name' => $highestIncomeCategory->name,
                'amount' => $highestIncomeCategory->total_amount
            ] : null,
        ];
    }
}
