<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\TransactionAudit;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionAuditResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountAuditController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorize('viewAudit', $account);

        $audits = TransactionAudit::query()
            ->where('account_id', $account->id)
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate((int) $request->get('perPage', 25));

        return response()->json([
            'account' => new AccountResource($account->loadCount('transactions')),
            'data' => TransactionAuditResource::collection($audits->items()),
            'paginatorInfo' => [
                'hasMorePages' => $audits->hasMorePages(),
                'currentPage' => $audits->currentPage(),
                'lastPage' => $audits->lastPage(),
                'perPage' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }
}