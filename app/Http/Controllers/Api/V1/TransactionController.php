<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
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
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Scopes\OwnedAccountScope;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly GetTransactionsQueryHandler $getTransactionsQueryHandler,
        private readonly CreateTransactionCommandHandler $createTransactionCommandHandler,
        private readonly UpdateTransactionCommandHandler $updateTransactionCommandHandler,
        private readonly DeleteTransactionCommandHandler $deleteTransactionCommandHandler
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = new GetTransactionsQuery(
            perPage: (int) $request->get('perPage', 50)
        );

        return $this->getTransactionsQueryHandler->handle($query)->toResponse();
    }

    public function store(CreateTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = Account::query()->accessibleTo($request->user())->findOrFail((int) $validated['account_id']);
        $this->authorize('create', [Transaction::class, $account]);

        if (($validated['create_reverse_transaction'] ?? false) && ! empty($validated['reverse_account_id'])) {
            $reverseAccount = Account::query()
                ->accessibleTo($request->user())
                ->findOrFail((int) $validated['reverse_account_id']);

            $this->authorize('create', [Transaction::class, $reverseAccount]);
        }

        $command = new CreateTransactionCommand(
            data: $validated,
            userId: (int) $request->user()->id,
        );

        return $this->createTransactionCommandHandler->handle($command)->toResponse();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::query()
            ->withoutGlobalScope(OwnedAccountScope::class)
            ->forAccessibleAccounts($request->user())
            ->with(['account.user:id,name', 'account.sharedUsers:id,name,email', 'category.user:id,name'])
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

        $targetAccountId = (int) $request->validated()['account_id'];

        if ($targetAccountId !== (int) $transaction->account_id) {
            $targetAccount = Account::query()->accessibleTo($request->user())->findOrFail($targetAccountId);
            $this->authorize('create', [Transaction::class, $targetAccount]);
        }

        $command = new UpdateTransactionCommand(
            id: $id,
            data: $request->validated()
        );

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

        $command = new DeleteTransactionCommand(
            id: $id
        );

        return $this->deleteTransactionCommandHandler->handle($command)->toResponse();
    }

    public function formOptions(Request $request): JsonResponse
    {
        $ownerIds = $request->filled('account_id')
            ? collect([
                (int) Account::query()
                    ->accessibleTo($request->user())
                    ->findOrFail((int) $request->input('account_id'))
                    ->user_id,
            ])
            : Account::query()
                ->accessibleTo($request->user())
                ->pluck('user_id')
                ->map(fn (mixed $userId) => (int) $userId)
            ->unique()
            ->values();

        $categories = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('user_id', $ownerIds->all())
            ->with('user:id,name')
            ->withCount('transactions')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}

