<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_type' => $this->transaction_type,
            'note' => $this->note,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'canEdit' => $this->account?->canBeEditedBy($user) ?? false,
            'account' => $this->whenLoaded('account', function () {
                $owner = $this->account->relationLoaded('user') ? $this->account->user : null;

                return [
                    'id' => $this->account->id,
                    'name' => $this->account->getLocalizedName(),
                    'name_translations' => $this->account->getTranslations('name'),
                    'balance' => (float) $this->account->balance,
                    'currency' => $this->account->currency,
                    'isOwner' => $this->account->isOwnedBy(request()->user()),
                    'ownerId' => $this->account->user_id,
                    'ownerName' => isset($owner->name) ? $owner->name : null,
                    'participantUserIds' => $this->account->participantUserIds(),
                    'permissionLevel' => $this->account->isOwnedBy(request()->user()) ? 'owner' : $this->account->permissionLevelFor(request()->user()),
                    'canEditTransactions' => $this->account->canBeEditedBy(request()->user()),
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                if (! $this->category) {
                    return null;
                }

                $owner = $this->category->relationLoaded('user') ? $this->category->user : null;

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->getTranslation('name', app()->getLocale(), false) ?: $this->category->getTranslation('name', 'en', false),
                    'name_translations' => $this->category->getTranslations('name'),
                    'ownerUserId' => $this->category->user_id,
                    'ownerName' => $owner ? $owner->name : null,
                    'type' => $this->category->type,
                    'color' => $this->category->color,
                    'icon' => $this->category->icon,
                ];
            }),
        ];
    }
}
