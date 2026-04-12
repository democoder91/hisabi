<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use Carbon\Carbon;

class NetWorthTrendMetric extends Metric
{
    public function calculate(): array
    {
        $income = $this->transactions(fn ($query) => $query->income())
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->map(fn ($transactions) => $this->sumConverted($transactions));

        $expenses = $this->transactions(fn ($query) => $query->expenses())
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->map(fn ($transactions) => $this->sumConverted($transactions));

        $allLabels = $income->keys()->merge($expenses->keys())->unique()->sort()->values();

        $runningNetWorth = 0;
        $results = [];

        foreach ($allLabels as $label) {
            $incomeValue = $income->get($label, 0);
            $expenseValue = $expenses->get($label, 0);
            $runningNetWorth += ($incomeValue - $expenseValue);
            $results[] = ['label' => $label, 'value' => $runningNetWorth];
        }

        if ($this->hasDateRange()) {
            $results = array_filter($results, function ($item) {
                $itemDate = $item['label'] . '-01';
                return $itemDate >= $this->getStartDate() && $itemDate <= $this->getEndDate();
            });
            $results = array_values($results);
        }

        return $this->itemsPayload($results);
    }
}
