<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class TotalIncomeMetric extends Metric
{
    public function calculate(): array
    {
        $current = $this->sumAccountTypeMovement(Account::TYPE_INCOME);

        $previous = 0;
        $previousRange = $this->getPreviousRange();
        if ($previousRange) {
            $previous = $this->sumAccountTypeMovement(Account::TYPE_INCOME, $previousRange);
        }

        return $this->valuePayload($current, $previous);
    }
}
