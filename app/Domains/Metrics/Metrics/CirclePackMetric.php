<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Metrics\Metric;

class CirclePackMetric extends Metric
{
    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('categories.name');

        $colors = [
            'red' => '#ef4444',
            'blue' => '#3b82f6',
            'green' => '#22c55e',
            'orange' => '#f97316',
            'purple' => '#A754F7',
            'pink' => '#ec4899',
            'indigo' => '#6366f1',
            'gray' => '#94A4B8'
        ];

        $rootLevel = [
            'currency' => $this->metricCurrency(),
            'children' => [],
        ];

        $transactions = $this->transactions();

        foreach ($transactions->groupBy(fn ($transaction) => $transaction->category ? $transaction->category->type : 'Unknown') as $key => $value) {
            $rootLevel["children"][] = [
                "label" => $key,
                "children" => $value->map(function ($item) use ($colors) {
                    return [
                        "label" => $this->categoryLabel($item->category),
                        "value" => $this->convertedTransactionAmount($item),
                        "color" => $colors[$item->category ? $item->category->color : ''] ?? 'white'
                    ];
                })->groupBy('label')->map(function ($children) {
                    $firstChild = $children->first();
                    $firstChild['value'] = round($children->sum('value'), 2);

                    return $firstChild;
                })->values()->toArray()
            ];
        }

        return $rootLevel;
    }
}
