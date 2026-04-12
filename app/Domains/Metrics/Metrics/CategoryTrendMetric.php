<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use Carbon\Carbon;

class CategoryTrendMetric extends Metric
{
    protected int $categoryId;

    public function __construct(?string $from, ?string $to, int $categoryId)
    {
        parent::__construct($from, $to);
        $this->categoryId = $categoryId;
    }

    public function calculate(): array
    {
        $items = $this->transactions(fn ($query) => $query->where('transactions.category_id', $this->categoryId))
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => $this->sumConverted($transactions),
            ])
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
