<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class IncomeByAccountMetric extends Metric
{
    public function calculate(): array
    {
        $items = $this->transactions(fn ($query) => $query->whereHas('fromAccount', function ($accountQuery) {
            $accountQuery->where('type', Account::TYPE_INCOME);
        }))
            ->groupBy(fn ($transaction) => (int) $transaction->from_account_id)
            ->map(function ($transactions) {
                $firstTransaction = $transactions->first();
                $account = $firstTransaction ? $firstTransaction->fromAccount : null;

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