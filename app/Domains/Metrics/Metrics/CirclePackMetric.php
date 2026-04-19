<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class CirclePackMetric extends Metric
{
    public function calculate(): array
    {
        $colors = [
            Account::TYPE_EXPENSE => '#ef4444',
            Account::TYPE_INCOME => '#3b82f6',
            Account::TYPE_ASSET => '#22c55e',
            Account::TYPE_LIABILITY => '#f97316',
            Account::TYPE_EQUITY => '#6366f1',
            'unknown' => '#94A4B8',
        ];

        $rootLevel = [
            'currency' => $this->metricCurrency(),
            'children' => [],
        ];

        $transactions = $this->transactions()->groupBy(fn ($transaction) => $transaction->reportingAccountType() ?? 'unknown');

        foreach ($transactions as $type => $items) {
            $rootLevel['children'][] = [
                'label' => $type === 'unknown' ? 'Unknown' : $this->accountTypeLabel($type),
                'children' => $items->groupBy(fn ($transaction) => $this->reportingAccountLabel($transaction))
                    ->map(function ($children, $label) use ($colors, $type) {
                        return [
                            'label' => $label,
                            'value' => round($children->sum(fn ($transaction) => $this->convertedTransactionAmount($transaction)), 2),
                            'color' => $colors[$type] ?? $colors['unknown'],
                        ];
                    })
                    ->values()
                    ->toArray(),
            ];
        }

        return $rootLevel;
    }
}
