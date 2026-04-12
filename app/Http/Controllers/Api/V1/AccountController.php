<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Account\Services\AccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateAccountRequest;
use App\Http\Requests\Api\V1\InviteAccountShareRequest;
use App\Http\Requests\Api\V1\UpdateAccountRequest;
use App\Http\Requests\Api\V1\UpdateAccountShareRequest;
use App\Http\Resources\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->accountService->getPaginated((int) $request->get('perPage', 50));

        return response()->json([
            'data' => AccountResource::collection($accounts->items()),
            'paginatorInfo' => [
                'hasMorePages' => $accounts->hasMorePages(),
                'currentPage' => $accounts->currentPage(),
                'lastPage' => $accounts->lastPage(),
                'perPage' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json([
            'data' => AccountResource::collection($this->accountService->getAll()),
        ]);
    }

    public function store(CreateAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->validated());

        return response()->json([
            'account' => new AccountResource($account->load(['sharedUsers:id,name,email'])->loadCount('transactions')),
        ], 201);
    }

    public function update(UpdateAccountRequest $request, int $id): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('update', $account);

        $account = $this->accountService->update($account, $request->validated());

        return response()->json([
            'account' => new AccountResource($account),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('delete', $account);

        $account = $this->accountService->delete($account);

        return response()->json([
            'account' => new AccountResource($account),
        ]);
    }

    public function searchShareableUsers(Request $request, int $id): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('manageSharing', $account);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $users = $this->accountService->searchShareableUsers($account, (string) ($validated['search'] ?? ''));

        return response()->json([
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all(),
        ]);
    }

    public function share(InviteAccountShareRequest $request, int $id): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('manageSharing', $account);

        $account = $this->accountService->invite($account, $request->validated());

        return response()->json([
            'account' => new AccountResource($account),
        ]);
    }

    public function updateShare(UpdateAccountShareRequest $request, int $id, int $shareUserId): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('manageSharing', $account);

        $account = $this->accountService->updateSharePermission(
            $account,
            $shareUserId,
            $request->validated()['permission_level']
        );

        return response()->json([
            'account' => new AccountResource($account),
        ]);
    }

    public function revokeShare(int $id, int $shareUserId): JsonResponse
    {
        $account = $this->accountService->findAccessibleOrFail($id);
        $this->authorize('manageSharing', $account);

        $account = $this->accountService->revokeShare($account, $shareUserId);

        return response()->json([
            'account' => new AccountResource($account),
        ]);
    }
}