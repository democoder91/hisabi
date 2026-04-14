<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', app()->getLocale(), false) ?: $this->getTranslation('name', 'en', false),
            'name_translations' => $this->getTranslations('name'),
            'ownerUserId' => $this->user_id,
            'ownerName' => $owner ? $owner->name : null,
            'type' => $this->type,
            'color' => $this->color,
            'icon' => $this->icon,
            'transactionsCount' => $this->transactions_count ?? 0,
        ];
    }
}
