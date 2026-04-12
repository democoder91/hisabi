<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->getLocalizedName(),
            'name_translations' => $this->getTranslations('name'),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'start_at' => $this->start_at?->format('Y-m-d'),
            'end_at' => $this->end_at?->format('Y-m-d'),
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
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->getTranslation('name', app()->getLocale(), false) ?: $category->getTranslation('name', 'en', false),
                'name_translations' => $category->getTranslations('name'),
                'color' => $category->color,
                'icon' => $category->icon,
            ])->values()->all(), []),
        ];
    }
}
