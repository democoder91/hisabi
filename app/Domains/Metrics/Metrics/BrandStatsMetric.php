<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BrandStatsMetric extends Metric
{
    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('brands.name');

        $query = Transaction::query();
        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        $mostUsedBrand = (clone $query)
            ->join('brands', 'transactions.brand_id', '=', 'brands.id')
            ->selectRaw("brands.id, {$labelExpression} as name, COUNT(transactions.id) as transaction_count")
            ->groupBy('brands.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('transaction_count', 'DESC')
            ->first();

        $highestSpendingBrand = (clone $query)
            ->join('brands', 'transactions.brand_id', '=', 'brands.id')
            ->join('categories', 'brands.category_id', '=', 'categories.id')
            ->selectRaw("brands.id, {$labelExpression} as name, SUM(transactions.amount) as total_amount")
            ->where('categories.type', Category::EXPENSES)
            ->groupBy('brands.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('total_amount', 'DESC')
            ->first();

        $highestIncomeBrand = (clone $query)
            ->join('brands', 'transactions.brand_id', '=', 'brands.id')
            ->join('categories', 'brands.category_id', '=', 'categories.id')
            ->selectRaw("brands.id, {$labelExpression} as name, SUM(transactions.amount) as total_amount")
            ->where('categories.type', Category::INCOME)
            ->groupBy('brands.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('total_amount', 'DESC')
            ->first();

        return [
            'mostUsedBrand' => $mostUsedBrand ? [
                'name' => $mostUsedBrand->name,
                'count' => $mostUsedBrand->transaction_count
            ] : null,
            'highestSpendingBrand' => $highestSpendingBrand ? [
                'name' => $highestSpendingBrand->name,
                'amount' => $highestSpendingBrand->total_amount
            ] : null,
            'highestIncomeBrand' => $highestIncomeBrand ? [
                'name' => $highestIncomeBrand->name,
                'amount' => $highestIncomeBrand->total_amount
            ] : null,
        ];
    }
}
