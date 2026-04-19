<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use Carbon\Carbon;

class AccountTrendMetric extends Metric
{
    protected int $accountId;

    public function __construct(?string $from, ?string $to, int $accountId)
    {
        parent::__construct($from, $to);
        $this->accountId = $accountId;
    }

    public function calculate(): array
    {
        $items = $this->transactions(function ($query) {
            $query->where(function ($builder) {
                $builder->where('transactions.account_id', $this->accountId)
                    ->orWhere('transactions.from_account_id', $this->accountId)
                    ->orWhere('transactions.to_account_id', $this->accountId);
            });
        })
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($transactions, $label) => [
                'label' => $label,
                'value' => $this->sumConverted($transactions),
            ])
            ->values()
            ->all();

        return $this->itemsPayload($items);
    }
}