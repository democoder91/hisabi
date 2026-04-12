<?php

namespace App\Http\Commands\Transaction\UpdateTransactionCommand;

use App\Domains\Transaction\Models\Transaction;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

readonly class UpdateTransactionCommandResponse
{
    public function __construct(
        private Transaction $transaction
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'transaction' => new TransactionResource($this->transaction->load(['category', 'account'])),
        ]);
    }
}
