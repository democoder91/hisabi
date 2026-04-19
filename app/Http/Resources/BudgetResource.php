<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startAt = $this->start_at;
        $endAt = $this->end_at;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->getLocalizedName(),
            'name_translations' => $this->getSafeNameTranslations(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'start_at' => $startAt ? $startAt->format('Y-m-d') : null,
            'end_at' => $endAt ? $endAt->format('Y-m-d') : null,
            'saving' => $this->saving,
            'period' => $this->period,
            'reoccurrence' => $this->reoccurrence,
            'total_spent_percentage' => $this->total_spent_percentage,
            'start_at_date' => $this->start_at_date,
            'end_at_date' => $this->end_at_date,
            'remaining_to_spend' => $this->remaining_to_spend,
            'total_margin_per_day' => $this->total_margin_per_day,
            'remaining_days' => $this->remaining_days,
            'elapsed_days_percentage' => $this->elapsed_days_percentage,
            'is_saving' => $this->is_saving,
            'total_transactions_amount' => $this->total_transactions_amount,
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'accountId' => $category->account_id,
                        'name' => $category->getLocalizedName(),
                        'name_translations' => $category->getSafeNameTranslations(),
                        'color' => $category->color,
                        'icon' => $category->icon,
                    ];
                })->values()->all();
            }, []),
        ];
    }
}
