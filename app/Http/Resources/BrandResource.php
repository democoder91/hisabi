<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', app()->getLocale(), false) ?: $this->getTranslation('name', 'en', false),
            'name_translations' => $this->getTranslations('name'),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->getTranslation('name', app()->getLocale(), false) ?: $this->category->getTranslation('name', 'en', false),
                    'name_translations' => $this->category->getTranslations('name'),
                    'color' => $this->category->color,
                    'icon' => $this->category->icon,
                ];
            }),
            'transactionsCount' => $this->transactions_count ?? 0,
        ];
    }
}
