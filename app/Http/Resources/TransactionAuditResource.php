<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];
        $allKeys = array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)]));

        $changedFields = array_values(array_filter($allKeys, function (string $key) use ($oldValues, $newValues) {
            return ($oldValues[$key] ?? null) !== ($newValues[$key] ?? null);
        }));

        return [
            'id' => $this->id,
            'transactionId' => $this->transaction_id,
            'accountId' => $this->account_id,
            'action' => $this->action,
            'oldValues' => $oldValues,
            'newValues' => $newValues,
            'changedFields' => $changedFields,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
        ];
    }
}