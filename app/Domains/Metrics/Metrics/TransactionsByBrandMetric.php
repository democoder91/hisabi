<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionsByBrandMetric extends Metric
{
    protected int $categoryId;

    public function __construct(?string $from, ?string $to, int $categoryId)
    {
        parent::__construct($from, $to);
        $this->categoryId = $categoryId;
    }

    public function calculate(): array
    {
        $labelExpression = $this->localizedJsonValueExpression('brands.name');

        $query = Transaction::query()
            ->join('brands', 'brands.id', '=', 'transactions.brand_id')
            ->where('brands.category_id', $this->categoryId)
            ->selectRaw($this->localizedJsonSelect('brands.name') . ', count(transactions.id) as value')
            ->groupBy('brands.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
