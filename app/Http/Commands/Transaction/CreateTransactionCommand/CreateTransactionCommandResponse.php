<?php

namespace App\Http\Commands\Transaction\CreateTransactionCommand;

use App\Domains\Transaction\Models\Transaction;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

readonly class CreateTransactionCommandResponse
{
    public function __construct(
        private Transaction $transaction
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'transaction' => new TransactionResource($this->transaction->load(['category', 'account'])),
        ], 201);
    }
}
