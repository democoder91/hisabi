<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Category\Models\Category;

class ExpensesByCategoryMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions(fn ($query) => $query->expenses())
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => $this->sumConverted($transactions),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
