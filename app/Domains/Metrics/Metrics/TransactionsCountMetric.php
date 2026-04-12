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
            ->leftJoin('brands', 'brands.id', '=', 'transactions.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'brands.category_id')
            ->select(DB::raw("COALESCE(categories.type, 'UNCATEGORIZED') as label"), DB::raw("count(transactions.id) as value"))
            ->groupBy(DB::raw("COALESCE(categories.type, 'UNCATEGORIZED')"))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
