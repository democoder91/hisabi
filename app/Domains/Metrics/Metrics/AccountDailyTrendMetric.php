<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use Carbon\Carbon;

class AccountDailyTrendMetric extends Metric
{
    protected int $accountId;

    public function __construct(?string $from, ?string $to, int $accountId)
    {
        parent::__construct($from, $to);
        $this->accountId = $accountId;
    }

    public function calculate(): array
    {
        if (! $this->hasDateRange()) {
            return $this->itemsPayload([]);
        }

        $transactions = $this->transactions(function ($query) {
            $query->where(function ($builder) {
                $builder->where('transactions.account_id', $this->accountId)
                    ->orWhere('transactions.from_account_id', $this->accountId)
                    ->orWhere('transactions.to_account_id', $this->accountId);
            });
        })
            ->groupBy(fn ($transaction) => Carbon::parse($transaction->created_at)->format('Y-m-d'))
            ->map(fn ($items) => $this->sumConverted($items));

        $startDate = Carbon::parse($this->getStartDate());
        $endDate = Carbon::parse($this->getEndDate());
        $currentDate = $startDate->copy();
        $results = [];

        while ($currentDate->lte($endDate)) {
            $date = $currentDate->format('Y-m-d');
            $results[] = [
                'label' => $date,
                'value' => $transactions->get($date, 0),
            ];
            $currentDate->addDay();
        }

        return $this->itemsPayload($results);
    }
}