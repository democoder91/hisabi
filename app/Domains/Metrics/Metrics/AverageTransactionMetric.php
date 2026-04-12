<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class AverageTransactionMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions()
            ->groupBy(fn ($transaction) => $this->categoryLabel($transaction->category))
            ->map(function ($transactions, $label) {
                $converted = $transactions->map(fn ($transaction) => $this->convertedTransactionAmount($transaction));

                return [
                    'label' => $label,
                    'value' => round($converted->avg() ?? 0, 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
