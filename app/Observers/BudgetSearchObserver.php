<?php

namespace App\Observers;

use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Services\SemanticSearchIndexer;

class BudgetSearchObserver
{
    public function __construct(
        private readonly SemanticSearchIndexer $indexer,
    ) {
    }

    public function created(Budget $budget): void
    {
        $this->indexer->index($budget);
    }

    public function updated(Budget $budget): void
    {
        if (! $budget->wasChanged('name')) {
            return;
        }

        $this->indexer->index($budget);
    }

    public function deleted(Budget $budget): void
    {
        if ($budget->isForceDeleting() || $budget->trashed()) {
            $this->indexer->deleteFor($budget);
        }
    }

    public function restored(Budget $budget): void
    {
        $this->indexer->index($budget);
    }

    public function forceDeleted(Budget $budget): void
    {
        $this->indexer->deleteFor($budget);
    }
}
