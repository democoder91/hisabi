<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TotalExpensesMetric extends Metric
{
    public function calculate(): array
    {
        $currentTransactions = $this->transactions(fn ($query) => $query->expenses());

        $previous = 0;
        $previousRange = $this->getPreviousRange();
        if ($previousRange) {
            $previous = $this->sumConverted($this->transactions(fn ($query) => $query->expenses(), $previousRange));
        }

        return $this->valuePayload($this->sumConverted($currentTransactions), $previous);
    }
}
