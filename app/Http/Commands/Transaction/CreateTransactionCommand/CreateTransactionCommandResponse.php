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
            ->map(fn (Transaction $transaction) => $transaction->load([
                'account.user:id,name',
                'account.sharedUsers:id,name,email',
                'fromAccount.user:id,name',
                'toAccount.user:id,name',
            ]))
            ->values();

        return response()->json([
            'transaction' => new TransactionResource($transactions->first()),
            'transactions' => TransactionResource::collection($transactions),
        ], 201);
    }
}
