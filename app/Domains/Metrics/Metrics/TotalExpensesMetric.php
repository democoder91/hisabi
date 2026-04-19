<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class TotalExpensesMetric extends Metric
{
    public function calculate(): array
    {
        $current = $this->sumAccountTypeMovement(Account::TYPE_EXPENSE);

        $previous = 0;
        $previousRange = $this->getPreviousRange();
        if ($previousRange) {
            $previous = $this->sumAccountTypeMovement(Account::TYPE_EXPENSE, $previousRange);
        }

        return $this->valuePayload($current, $previous);
    }
}
