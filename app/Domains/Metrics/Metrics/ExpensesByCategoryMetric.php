<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Category\Models\Category;
use Illuminate\Support\Facades\DB;

class ExpensesByCategoryMetric extends Metric
{
    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('categories.name');

        $query = Category::query()
            ->where('type', Category::EXPENSES)
            ->join('transactions', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw($this->localizedJsonSelect('categories.name') . ', SUM(transactions.amount) as value')
            ->groupBy('categories.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
