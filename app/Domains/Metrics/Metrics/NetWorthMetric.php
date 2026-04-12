<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class NetWorthMetric extends Metric
{
    public function calculate(): array
    {
        $income = $this->sumConverted($this->transactions(fn ($query) => $query->income()));
        $expenses = $this->sumConverted($this->transactions(fn ($query) => $query->expenses()));

        return $this->valuePayload($income - $expenses);
    }
}
