<?php

namespace App\Policies;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $account->canBeViewedBy($user);
    }

    public function update(User $user, Account $account): bool
    {
        return $account->isOwnedBy($user);
    }

    public function delete(User $user, Account $account): Response
    {
        if (! $account->isOwnedBy($user)) {
            return Response::deny('You do not own this account.');
        }

        if ($user->accounts()->count() <= 1) {
            return Response::deny('You must have at least one account.');
        }

        return Response::allow();
    }

    public function manageSharing(User $user, Account $account): bool
    {
        return $account->isOwnedBy($user);
    }

    public function viewAudit(User $user, Account $account): bool
    {
        return $account->isOwnedBy($user);
    }
}