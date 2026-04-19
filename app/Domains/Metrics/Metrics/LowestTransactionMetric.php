<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class LowestTransactionMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions()
            ->groupBy(fn ($transaction) => $this->reportingAccountLabel($transaction))
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => round($transactions->min(fn ($transaction) => $this->convertedTransactionAmount($transaction)), 2),
            ])
            ->sortBy('value')
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
