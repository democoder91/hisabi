<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $owner = $this->whenLoaded('user');

        return [
            'id' => $this->id,
            'name' => $this->getLocalizedName(),
            'name_translations' => $this->getSafeNameTranslations(),
            'balance' => (float) $this->balance,
            'currency' => $this->currency,
            'transactionsCount' => $this->transactions_count ?? 0,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'isOwner' => $this->isOwnedBy($user),
            'ownerId' => $this->user_id,
            'ownerName' => isset($owner->name) ? $owner->name : null,
            'participantUserIds' => $this->participantUserIds(),
            'canManage' => $this->isOwnedBy($user),
            'canViewAudit' => $this->isOwnedBy($user),
            'canEditTransactions' => $this->canBeEditedBy($user),
            'permissionLevel' => $this->isOwnedBy($user) ? 'owner' : $this->permissionLevelFor($user),
            'sharedUsers' => $this->when(
                $this->relationLoaded('sharedUsers') && $this->isOwnedBy($user),
                fn() => $this->sharedUsers->map(fn($sharedUser) => [
                    'id' => $sharedUser->id,
                    'name' => $sharedUser->name,
                    'email' => $sharedUser->email,
                    'permissionLevel' => $sharedUser->pivot->permission_level,
                ])->values()->all(),
                []
            ),
        ];
    }
}
