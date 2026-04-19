<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Queries\Transaction\GetTransactionsQuery\GetTransactionsQuery;
use App\Http\Queries\Transaction\GetTransactionsQuery\GetTransactionsQueryHandler;
use App\Http\Commands\Transaction\CreateTransactionCommand\CreateTransactionCommand;
use App\Http\Commands\Transaction\CreateTransactionCommand\CreateTransactionCommandHandler;
use App\Http\Commands\Transaction\UpdateTransactionCommand\UpdateTransactionCommand;
use App\Http\Commands\Transaction\UpdateTransactionCommand\UpdateTransactionCommandHandler;
use App\Http\Commands\Transaction\DeleteTransactionCommand\DeleteTransactionCommand;
use App\Http\Commands\Transaction\DeleteTransactionCommand\DeleteTransactionCommandHandler;
use App\Http\Requests\Api\V1\CreateTransactionRequest;
use App\Http\Requests\Api\V1\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Scopes\OwnedAccountScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    private GetTransactionsQueryHandler $getTransactionsQueryHandler;
    private CreateTransactionCommandHandler $createTransactionCommandHandler;
    private UpdateTransactionCommandHandler $updateTransactionCommandHandler;
    private DeleteTransactionCommandHandler $deleteTransactionCommandHandler;

    public function __construct(
        GetTransactionsQueryHandler $getTransactionsQueryHandler,
        CreateTransactionCommandHandler $createTransactionCommandHandler,
        UpdateTransactionCommandHandler $updateTransactionCommandHandler,
        DeleteTransactionCommandHandler $deleteTransactionCommandHandler
    ) {
        $this->getTransactionsQueryHandler = $getTransactionsQueryHandler;
        $this->createTransactionCommandHandler = $createTransactionCommandHandler;
        $this->updateTransactionCommandHandler = $updateTransactionCommandHandler;
        $this->deleteTransactionCommandHandler = $deleteTransactionCommandHandler;
    }

    public function index(Request $request): JsonResponse
    {
        $query = new GetTransactionsQuery((int) $request->get('perPage', 50));

        return $this->getTransactionsQueryHandler->handle($query)->toResponse();
    }

    public function store(CreateTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach (['from_account_id', 'to_account_id'] as $accountKey) {
            if (empty($validated[$accountKey])) {
                continue;
            }

            $ledgerAccount = Account::query()->accessibleTo($request->user())->findOrFail((int) $validated[$accountKey]);
            $this->authorize('create', [Transaction::class, $ledgerAccount]);
        }

        $command = new CreateTransactionCommand($validated, (int) $request->user()->id);

        return $this->createTransactionCommandHandler->handle($command)->toResponse();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($request->user())
            ->with([
                'account.user:id,name',
                'account.sharedUsers:id,name,email',
                'fromAccount.user:id,name',
                'toAccount.user:id,name',
            ])
            ->findOrFail($id);

        $this->authorize('view', $transaction);

        return response()->json([
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    public function update(UpdateTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($request->user())
            ->with(['account.user:id,name', 'account.sharedUsers:id,name,email'])
            ->findOrFail($id);

        $this->authorize('update', $transaction);

        $validated = $request->validated();

        foreach (['from_account_id', 'to_account_id'] as $accountKey) {
            if (empty($validated[$accountKey])) {
                continue;
            }

            $ledgerAccount = Account::query()->accessibleTo($request->user())->findOrFail((int) $validated[$accountKey]);
            $this->authorize('create', [Transaction::class, $ledgerAccount]);
        }

        $command = new UpdateTransactionCommand($id, $validated);

        return $this->updateTransactionCommandHandler->handle($command)->toResponse();
    }

    public function destroy(int $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts(request()->user())
            ->with(['account.user:id,name', 'account.sharedUsers:id,name,email'])
            ->findOrFail($id);

        $this->authorize('delete', $transaction);

        $command = new DeleteTransactionCommand($id);

        return $this->deleteTransactionCommandHandler->handle($command)->toResponse();
    }
}
