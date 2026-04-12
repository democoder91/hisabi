<?php

namespace App\Policies;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->account?->canBeViewedBy($user) ?? false;
    }

    public function create(User $user, Account $account): bool
    {
        return $account->canBeEditedBy($user);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->account?->canBeEditedBy($user) ?? false;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $transaction->account?->canBeEditedBy($user) ?? false;
    }
}