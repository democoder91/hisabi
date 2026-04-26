<?php

namespace App\Observers;

use App\Domains\Search\Services\SemanticSearchIndexer;
use App\Domains\Transaction\Models\Transaction;

class TransactionSearchObserver
{
    public function __construct(
        private readonly SemanticSearchIndexer $indexer,
    ) {
    }

    public function created(Transaction $transaction): void
    {
        $this->indexer->index($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('note') && ! $transaction->wasChanged('description')) {
            return;
        }

        $this->indexer->index($transaction);
    }

    public function deleted(Transaction $transaction): void
    {
        if ($transaction->isForceDeleting() || $transaction->trashed()) {
            $this->indexer->deleteFor($transaction);
        }
    }

    public function restored(Transaction $transaction): void
    {
        $this->indexer->index($transaction);
    }

    public function forceDeleted(Transaction $transaction): void
    {
        $this->indexer->deleteFor($transaction);
    }
}
