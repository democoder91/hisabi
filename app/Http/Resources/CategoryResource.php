<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('user') ? $this->user : null;
        $account = $this->relationLoaded('account') ? $this->account : null;

        return [
            'id' => $this->id,
            'name' => $this->getLocalizedName(),
            'name_translations' => $this->getSafeNameTranslations(),
            'ownerUserId' => $this->user_id,
            'ownerName' => $owner ? $owner->name : null,
            'accountId' => $account ? $account->id : null,
            'type' => $this->type,
            'color' => $this->color,
            'icon' => $this->icon,
            'transactionsCount' => $this->transactions_count ?? 0,
        ];
    }
}
