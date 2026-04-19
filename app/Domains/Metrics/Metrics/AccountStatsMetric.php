<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class AccountStatsMetric extends Metric
{
    public function calculate(): array
    {
        $transactions = $this->transactions();

        $mostUsedAccount = $transactions
            ->flatMap(function ($transaction) {
                return collect([$transaction->account, $transaction->fromAccount, $transaction->toAccount])
                    ->filter(fn ($account) => $account instanceof Account)
                    ->unique('id')
                    ->values();
            })
            ->groupBy(fn (Account $account) => (int) $account->id)
            ->map(fn ($accounts) => [
                'name' => $this->accountLabel($accounts->first()),
                'count' => $accounts->count(),
            ])
            ->sortByDesc('count')
            ->first();

        $highestSpendingAccount = $transactions
            ->filter(fn ($transaction) => $transaction->toAccount && $transaction->toAccount->type === Account::TYPE_EXPENSE)
            ->groupBy(fn ($transaction) => (int) $transaction->to_account_id)
            ->map(function ($items) {
                $firstTransaction = $items->first();

                return [
                    'name' => $this->accountLabel($firstTransaction ? $firstTransaction->toAccount : null),
                    'amount' => $this->sumConverted($items),
                ];
            })
            ->sortByDesc('amount')
            ->first();

        $highestIncomeAccount = $transactions
            ->filter(fn ($transaction) => $transaction->fromAccount && $transaction->fromAccount->type === Account::TYPE_INCOME)
            ->groupBy(fn ($transaction) => (int) $transaction->from_account_id)
            ->map(function ($items) {
                $firstTransaction = $items->first();

                return [
                    'name' => $this->accountLabel($firstTransaction ? $firstTransaction->fromAccount : null),
                    'amount' => $this->sumConverted($items),
                ];
            })
            ->sortByDesc('amount')
            ->first();

        return [
            'mostUsedAccount' => $mostUsedAccount ?: null,
            'highestSpendingAccount' => $highestSpendingAccount ?: null,
            'highestIncomeAccount' => $highestIncomeAccount ?: null,
            'currency' => $this->metricCurrency(),
        ];
    }
}