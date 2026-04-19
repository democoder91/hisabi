<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class NetWorthTrendMetric extends Metric
{
    public function calculate(): array
    {
        $assetAccounts = $this->accounts(fn(Builder $query) => $query->where('type', Account::TYPE_ASSET));
        $liabilityAccounts = $this->accounts(fn(Builder $query) => $query->where('type', Account::TYPE_LIABILITY));

        $monthlyGroups = $this->transactions()
            ->groupBy(fn($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->sortKeys();

        $runningNetWorth = 0;
        $results = [];

        foreach ($monthlyGroups as $label => $transactions) {
            $assets = $this->sumAccountMovements($assetAccounts, $transactions);
            $liabilities = $this->sumAccountMovements($liabilityAccounts, $transactions);

            $runningNetWorth += ($assets - $liabilities);
            $results[] = ['label' => $label, 'value' => $runningNetWorth];
        }

        return $this->itemsPayload($results);
    }
}
