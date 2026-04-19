<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class ExpensesByAccountMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions(fn ($query) => $query->whereHas('toAccount', function ($accountQuery) {
            $accountQuery->where('type', Account::TYPE_EXPENSE);
        }))
            ->groupBy(fn ($transaction) => (int) $transaction->to_account_id)
            ->map(function ($transactions) {
                $firstTransaction = $transactions->first();
                $account = $firstTransaction ? $firstTransaction->toAccount : null;

                return [
                    'label' => $this->accountLabel($account),
                    'value' => $this->sumConverted($transactions),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}