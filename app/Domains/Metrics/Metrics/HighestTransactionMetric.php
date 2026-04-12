<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class HighestTransactionMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions()
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => round($transactions->max(fn ($transaction) => $this->convertedTransactionAmount($transaction)), 2),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
