<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AverageTransactionMetric extends Metric
{
    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('categories.name');

        $query = Transaction::query()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw($this->localizedJsonSelect('categories.name') . ', avg(transactions.amount) as value')
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
