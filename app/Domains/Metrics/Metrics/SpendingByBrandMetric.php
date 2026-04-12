<?php

namespace App\Domains\Metrics\Metrics;

use App\Domains\Metrics\Metric;
use App\Domains\Brand\Models\Brand;
use Illuminate\Support\Facades\DB;

class SpendingByBrandMetric extends Metric
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

        $query = Brand::query()
            ->where('category_id', $this->categoryId)
            ->join('transactions', 'transactions.brand_id', '=', 'brands.id')
            ->selectRaw($this->localizedJsonSelect('brands.name') . ', SUM(transactions.amount) as value')
            ->groupBy('brands.id')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy('value', 'DESC');

        if ($this->hasDateRange()) {
            $query->whereBetween('transactions.created_at', [$this->getStartDate(), $this->getEndDate()]);
        }

        return $query->get()->toArray();
    }
}
