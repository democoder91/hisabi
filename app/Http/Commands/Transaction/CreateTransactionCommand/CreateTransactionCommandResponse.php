<?php

namespace App\Http\Commands\Transaction\CreateTransactionCommand;

use App\Domains\Transaction\Models\Transaction;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

readonly class CreateTransactionCommandResponse
{
    public function __construct(
        private array $transactions
    ) {}

    public function toResponse(): JsonResponse
    {
        $transactions = collect($this->transactions)
            ->map(fn (Transaction $transaction) => $transaction->load(['category.user:id,name', 'account.user:id,name', 'account.sharedUsers:id,name,email']))
            ->values();

        return response()->json([
            'transaction' => new TransactionResource($transactions->first()),
            'transactions' => TransactionResource::collection($transactions),
        ], 201);
    }
}
