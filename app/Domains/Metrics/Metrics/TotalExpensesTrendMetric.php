<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TotalExpensesTrendMetric extends Metric
{
    public function calculate(): array
    {
        $expenseAccounts = $this->accounts(fn(Builder $query) => $query->where('type', Account::TYPE_EXPENSE));

        $items = $this->transactions()
            ->groupBy(fn($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn($transactions, $label) => [
                'label' => $label,
                'value' => $this->sumAccountMovements($expenseAccounts, $transactions),
            ])
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}
