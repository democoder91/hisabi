<?php

namespace App\Console\Commands;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Search\Services\SemanticSearchIndexer;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class RebuildSearchIndexCommand extends Command
{
    protected $signature = 'search:rebuild
        {--type=* : Limit to one or more entity types: accounts, transactions, budgets}
        {--user= : Optional user id to limit indexing to a specific user}
        {--chunk=100 : Number of records to process per chunk}';

    protected $description = 'Rebuild the semantic search index for accounts, transactions, and budgets.';

    public function handle(SemanticSearchIndexer $indexer): int
    {
        $types = $this->resolveTypes();
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $chunkSize = max(10, (int) $this->option('chunk'));

        $totals = [];

        foreach ($types as $type => $modelClass) {
            $this->info("Indexing {$type}...");
            $indexed = 0;

            $builder = $modelClass::query();

            if ($userId !== null) {
                $builder->where('user_id', $userId);
            }

            $builder->orderBy('id')->chunkById($chunkSize, function ($models) use ($indexer, &$indexed) {
                foreach ($models as $model) {
                    /** @var Model $model */
                    try {
                        $indexer->index($model);
                        $indexed++;
                    } catch (Throwable $exception) {
                        $this->warn(sprintf(
                            '  Failed indexing %s #%s: %s',
                            $model->getMorphClass(),
                            $model->getKey(),
                            $exception->getMessage(),
                        ));
                    }
                }
            });

            $totals[$type] = $indexed;
            $this->line("  Indexed {$indexed} {$type} records.");
        }

        $this->info('Done.');

        foreach ($totals as $type => $count) {
            $this->line("  {$type}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, class-string>
     */
    private function resolveTypes(): array
    {
        $available = [
            'accounts' => Account::class,
            'transactions' => Transaction::class,
            'budgets' => Budget::class,
        ];

        $requested = (array) $this->option('type');

        if ($requested === []) {
            return $available;
        }

        $resolved = [];

        foreach ($requested as $key) {
            $key = strtolower((string) $key);

            if (! isset($available[$key])) {
                $this->warn("Unknown type '{$key}' ignored.");

                continue;
            }

            $resolved[$key] = $available[$key];
        }

        return $resolved === [] ? $available : $resolved;
    }
}
