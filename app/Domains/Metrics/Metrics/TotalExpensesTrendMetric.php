<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use Carbon\Carbon;

class TotalExpensesTrendMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions(fn ($query) => $query->expenses())
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
