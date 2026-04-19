<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Services\CategoryService;
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
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
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
    private CategoryService $categoryService;

    public function __construct(
        GetTransactionsQueryHandler $getTransactionsQueryHandler,
        CreateTransactionCommandHandler $createTransactionCommandHandler,
        UpdateTransactionCommandHandler $updateTransactionCommandHandler,
        DeleteTransactionCommandHandler $deleteTransactionCommandHandler,
        CategoryService $categoryService
    ) {
        $this->getTransactionsQueryHandler = $getTransactionsQueryHandler;
        $this->createTransactionCommandHandler = $createTransactionCommandHandler;
        $this->updateTransactionCommandHandler = $updateTransactionCommandHandler;
        $this->deleteTransactionCommandHandler = $deleteTransactionCommandHandler;
        $this->categoryService = $categoryService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = new GetTransactionsQuery((int) $request->get('perPage', 50));

        return $this->getTransactionsQueryHandler->handle($query)->toResponse();
    }

    public function store(CreateTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['account_id'])) {
            $account = Account::query()->accessibleTo($request->user())->findOrFail((int) $validated['account_id']);
            $this->authorize('create', [Transaction::class, $account]);
        }

        foreach (['from_account_id', 'to_account_id'] as $accountKey) {
            if (empty($validated[$accountKey])) {
                continue;
            }

            $ledgerAccount = Account::query()->accessibleTo($request->user())->findOrFail((int) $validated[$accountKey]);
            $this->authorize('create', [Transaction::class, $ledgerAccount]);
        }

        if (($validated['create_reverse_transaction'] ?? false) && ! empty($validated['reverse_account_id'])) {
            $reverseAccount = Account::query()
                ->accessibleTo($request->user())
                ->findOrFail((int) $validated['reverse_account_id']);

            $this->authorize('create', [Transaction::class, $reverseAccount]);
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
                'category.user:id,name',
                'category.account',
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
        $targetAccountId = (int) ($validated['account_id'] ?? $transaction->account_id);

        if ($targetAccountId !== (int) $transaction->account_id) {
            $targetAccount = Account::query()->accessibleTo($request->user())->findOrFail($targetAccountId);
            $this->authorize('create', [Transaction::class, $targetAccount]);
        }

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

    public function formOptions(Request $request): JsonResponse
    {
        $accessibleAccounts = Account::query()
            ->accessibleTo($request->user())
            ->with(['user:id,name', 'sharedUsers:id,name,email'])
            ->withCount('transactions')
            ->orderBy('id');

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
            ->map(fn(mixed $userId) => (int) $userId)
            ->unique()
            ->values();

        $this->categoryService->syncLedgerCategoriesForOwners($ownerIds->all());

        $categories = $this->categoryService->getAll()
            ->whereIn('user_id', $ownerIds->all())
            ->values();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
            'paymentMethods' => AccountResource::collection((clone $accessibleAccounts)->assets()->get()),
            'depositAccounts' => AccountResource::collection((clone $accessibleAccounts)->assets()->get()),
            'incomeSources' => AccountResource::collection((clone $accessibleAccounts)->incomes()->get()),
            'expenseAccounts' => AccountResource::collection((clone $accessibleAccounts)->expenses()->get()),
        ]);
    }
}
