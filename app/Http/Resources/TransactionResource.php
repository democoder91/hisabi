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
            'transaction_type' => $this->transaction_type,
            'note' => $this->note,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'canEdit' => $this->account?->canBeEditedBy($user) ?? false,
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->getLocalizedName(),
                    'name_translations' => $this->account->getTranslations('name'),
                    'balance' => (float) $this->account->balance,
                    'canEditTransactions' => $this->account->canBeEditedBy(request()->user()),
                ];
            }),
            'brand' => $this->whenLoaded('brand', function () {
                if (!$this->brand) {
                    return null;
                }

                return [
                    'id' => $this->brand->id,
                    'name' => $this->brand->name,
                    'category' => $this->brand->category ? [
                        'id' => $this->brand->category->id,
                        'name' => $this->brand->category->name,
                        'type' => $this->brand->category->type,
                        'color' => $this->brand->category->color,
                        'icon' => $this->brand->category->icon,
                    ] : null,
                ];
            }),
        ];
    }
}

