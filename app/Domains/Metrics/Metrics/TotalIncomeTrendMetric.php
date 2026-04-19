<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TotalIncomeTrendMetric extends Metric
{
    public function calculate(): array
    {
        $incomeAccounts = $this->accounts(fn(Builder $query) => $query->where('type', Account::TYPE_INCOME));

        $items = $this->transactions()
            ->groupBy(fn($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn($transactions, $label) => [
                'label' => $label,
                'value' => $this->sumAccountMovements($incomeAccounts, $transactions),
            ])
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
