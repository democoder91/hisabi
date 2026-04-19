<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class NetWorthMetric extends Metric
{
    public function calculate(): array
    {
        $assets = $this->sumAccountTypeMovement(Account::TYPE_ASSET);
        $liabilities = $this->sumAccountTypeMovement(Account::TYPE_LIABILITY);

        return $this->valuePayload($assets - $liabilities);
    }
}
