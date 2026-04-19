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
        $date = $this->date;
        $createdAt = $this->created_at;
        $account = $this->account;

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_type' => $this->transaction_type,
            'note' => $this->note,
            'date' => $date ? $date->format('Y-m-d') : null,
            'created_at' => $createdAt ? $createdAt->format('Y-m-d') : null,
            'canEdit' => $account ? $account->canBeEditedBy($user) : false,
            'account' => $this->whenLoaded('account', function () {
                $owner = $this->account->relationLoaded('user') ? $this->account->user : null;

                return [
                    'id' => $this->account->id,
                    'name' => $this->account->getLocalizedName(),
                    'name_translations' => $this->account->getSafeNameTranslations(),
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
            'fromAccount' => $this->whenLoaded('fromAccount', function () {
                if (! $this->fromAccount) {
                    return null;
                }

                return [
                    'id' => $this->fromAccount->id,
                    'name' => $this->fromAccount->getLocalizedName(),
                    'name_translations' => $this->fromAccount->getSafeNameTranslations(),
                    'type' => $this->fromAccount->type,
                    'currency' => $this->fromAccount->currency,
                ];
            }),
            'toAccount' => $this->whenLoaded('toAccount', function () {
                if (! $this->toAccount) {
                    return null;
                }

                return [
                    'id' => $this->toAccount->id,
                    'name' => $this->toAccount->getLocalizedName(),
                    'name_translations' => $this->toAccount->getSafeNameTranslations(),
                    'type' => $this->toAccount->type,
                    'currency' => $this->toAccount->currency,
                ];
            }),
        ];
    }
}
