<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TotalCashMetric extends Metric
{
    public function calculate(): array
    {
        $income = $this->sumConverted($this->transactions(fn ($query) => $query->income()));
        $expenses = $this->sumConverted($this->transactions(fn ($query) => $query->expenses()));
        $investment = $this->sumConverted($this->transactions(fn ($query) => $query->investment()));
        $savings = $this->sumConverted($this->transactions(fn ($query) => $query->savings()));

        return $this->valuePayload($income - ($expenses + $investment + $savings));
    }
}
