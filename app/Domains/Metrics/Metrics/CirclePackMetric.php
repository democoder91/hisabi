<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Metrics\Metric;
use Illuminate\Support\Facades\DB;

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

        $transactions = Category::query()
            ->join('transactions', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw($this->localizedJsonSelect('categories.name', 'category_name') . ', categories.type, categories.color, SUM(transactions.amount) as value')
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->groupBy('categories.type')
            ->groupBy('categories.color');

        if ($this->hasDateRange()) {
            $transactions->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        $rootLevel = ["children" => []];
        foreach ($transactions->get()->groupBy('type') as $key => $value) {
            $rootLevel["children"][] = [
                "label" => $key,
                "children" => $value->map(function ($item) use ($colors) {
                    return [
                        "label" => $item->category_name,
                        "value" => $item->value,
                        "color" => $colors[$item->color] ?? 'white'
                    ];
                })->toArray()
            ];
        }

        return $rootLevel;
    }
}
