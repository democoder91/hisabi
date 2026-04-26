<?php

namespace App\Observers;

use App\Domains\Account\Models\Account;
use App\Domains\Search\Services\SemanticSearchIndexer;

class AccountSearchObserver
{
    public function __construct(
        private readonly SemanticSearchIndexer $indexer,
    ) {
    }

    public function created(Account $account): void
    {
        $this->indexer->index($account);
    }

    public function updated(Account $account): void
    {
        if (! $account->wasChanged('name')) {
            return;
        }

        $this->indexer->index($account);
    }

    public function deleted(Account $account): void
    {
        if ($account->isForceDeleting() || $account->trashed()) {
            $this->indexer->deleteFor($account);
        }
    }

    public function restored(Account $account): void
    {
        $this->indexer->index($account);
    }

    public function forceDeleted(Account $account): void
    {
        $this->indexer->deleteFor($account);
    }
}
