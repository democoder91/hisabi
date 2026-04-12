<?php

namespace App\Http\Controllers;

use App\Domains\Account\Models\Account;
use App\Http\Resources\AccountResource;
use Inertia\Inertia;
use Inertia\Response;

class AccountAuditController extends Controller
{
    public function show(Account $account): Response
    {
        $this->authorize('viewAudit', $account);

        return Inertia::render('Account/Audit', [
            'account' => AccountResource::make($account->loadCount('transactions'))->resolve(request()),
        ]);
    }
}