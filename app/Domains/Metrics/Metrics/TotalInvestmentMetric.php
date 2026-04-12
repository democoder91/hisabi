<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;

class TotalInvestmentMetric extends Metric
{
    public function calculate(): array
    {
        return $this->valuePayload($this->sumConverted($this->transactions(fn ($query) => $query->investment())));
    }
}
