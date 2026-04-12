<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Category\Models\Category;

class CategoryStatsMetric extends Metric
{
    public function calculate(): array
    {
        $transactions = $this->transactions();

        $mostUsedCategory = $transactions
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(fn ($items, $label) => [
                'name' => $label,
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->first();

        $highestSpendingCategory = $transactions
            ->filter(fn ($transaction) => $transaction->category?->type === Category::EXPENSES)
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(fn ($items, $label) => [
                'name' => $label,
                'amount' => $this->sumConverted($items),
            ])
            ->sortByDesc('amount')
            ->first();

        $highestIncomeCategory = $transactions
            ->filter(fn ($transaction) => $transaction->category?->type === Category::INCOME)
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(fn ($items, $label) => [
                'name' => $label,
                'amount' => $this->sumConverted($items),
            ])
            ->sortByDesc('amount')
            ->first();

        return [
            'mostUsedCategory' => $mostUsedCategory ?: null,
            'highestSpendingCategory' => $highestSpendingCategory ?: null,
            'highestIncomeCategory' => $highestIncomeCategory ?: null,
            'currency' => $this->metricCurrency(),
        ];
    }
}
