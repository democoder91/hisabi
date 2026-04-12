<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TransactionsStdDevMetric extends Metric
{
    protected int $categoryId;

    public function __construct(?string $from, ?string $to, int $categoryId)
    {
        parent::__construct($from, $to);
        $this->categoryId = $categoryId;
    }

    public function calculate(): array
    {
        $amounts = $this->transactions(fn ($query) => $query->where('transactions.category_id', $this->categoryId))
            ->map(fn ($transaction) => $this->convertedTransactionAmount($transaction))
            ->values()
            ->all();

        if (count($amounts) < 2) {
            return $this->valuePayload(0);
        }

        $mean = array_sum($amounts) / count($amounts);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $amounts)) / (count($amounts) - 1);

        return $this->valuePayload(sqrt($variance));
    }
}
