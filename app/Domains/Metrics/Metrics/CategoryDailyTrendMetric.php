<?php

namespace App\Domains\Metrics\Metrics;

use Carbon\Carbon;
use App\Domains\Metrics\Metric;

class CategoryDailyTrendMetric extends Metric
{
    protected int $categoryId;

    public function __construct(?string $from, ?string $to, int $categoryId)
    {
        parent::__construct($from, $to);
        $this->categoryId = $categoryId;
    }

    public function calculate(): array
    {
        if (!$this->hasDateRange()) {
            return $this->itemsPayload([]);
        }

        $transactions = $this->transactions(fn ($query) => $query->where('transactions.category_id', $this->categoryId))
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
