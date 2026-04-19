<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TransactionsCountMetric extends Metric
{
    public function calculate(): array
    {
        return $this->transactions()
            ->groupBy(fn ($transaction) => $this->reportingAccountTypeLabel($transaction))
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => $transactions->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
