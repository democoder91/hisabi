<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TotalIncomeMetric extends Metric
{
    public function calculate(): array
    {
        $currentTransactions = $this->transactions(fn ($query) => $query->income());

        $previous = 0;
        $previousRange = $this->getPreviousRange();
        if ($previousRange) {
            $previous = $this->sumConverted($this->transactions(fn ($query) => $query->income(), $previousRange));
        }

        return $this->valuePayload($this->sumConverted($currentTransactions), $previous);
    }
}
