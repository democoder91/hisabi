<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionsCountMetric extends Metric
{
    public function calculate(): array
    {
        $query = Transaction::query()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select(DB::raw('categories.type as label'), DB::raw('count(transactions.id) as value'))
            ->groupBy(DB::raw('categories.type'))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
