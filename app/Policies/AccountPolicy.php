<?php

namespace App\Policies;

use App\Domains\Account\Models\Account;
use App\Models\User;

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

    public function delete(User $user, Account $account): bool
    {
        return $account->isOwnedBy($user);
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