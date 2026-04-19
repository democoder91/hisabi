<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Metrics\Metric;

class TotalAssetsMetric extends Metric
{
    public function calculate(): array
    {
        return $this->valuePayload($this->sumAccountTypeMovement(Account::TYPE_ASSET));
    }
}