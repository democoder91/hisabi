<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Finance\AiMigrationPromptBuilder;
use App\Services\Finance\LegacyTransactionLedgerMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RunAIMigration extends Command
{
    protected $signature = 'financial:ai-migrate {--limit=10 : Number of legacy transactions to migrate in this run}';

    protected $description = 'Migrate legacy financial transactions into the double-entry ledger using the archived AI migration mapping.';

    public function handle(
        LegacyTransactionLedgerMigrator $ledgerMigrator,
        AiMigrationPromptBuilder $promptBuilder
    ): int {
        if (! Schema::hasTable('legacy_transactions')) {
            $this->error('The legacy_transactions table was not found. Run the archive migration before starting the AI migration.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('legacy_categories')) {
            $this->error('The legacy_categories table was not found. Run the archive migration before starting the AI migration.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('legacy_accounts')) {
            $this->error('The legacy_accounts table was not found. Run the archive migration before starting the AI migration.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('categories')) {
            $this->error('The categories table was not found. The compatibility category catalog is still required for migration bookkeeping.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $transactions = DB::table('legacy_transactions')
            ->leftJoin('legacy_categories', 'legacy_categories.id', '=', 'legacy_transactions.category_id')
            ->leftJoin('legacy_accounts', 'legacy_accounts.id', '=', 'legacy_transactions.account_id')
            ->select([
                'legacy_transactions.id',
                'legacy_transactions.user_id',
                'legacy_transactions.account_id as legacy_account_id',
                'legacy_transactions.category_id as legacy_category_id',
                'legacy_transactions.amount',
                'legacy_transactions.currency',
                'legacy_transactions.transaction_type',
                'legacy_transactions.note as description',
                'legacy_transactions.note',
                'legacy_transactions.created_at as date',
                'legacy_transactions.updated_at as legacy_updated_at',
                'legacy_categories.name as category_name',
                'legacy_categories.type as category_type',
                'legacy_categories.color as category_color',
                'legacy_categories.icon as category_icon',
                'legacy_accounts.name as account_name',
                'legacy_accounts.currency as account_currency',
                'legacy_accounts.color as account_color',
                'legacy_accounts.icon as account_icon',
            ])
            ->where('legacy_transactions.is_migrated', false)
            ->orderBy('legacy_transactions.id')
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No legacy transactions are waiting for migration.');

            return self::SUCCESS;
        }

        foreach ($transactions as $legacyTransaction) {
            $user = User::query()->find($legacyTransaction->user_id);

            if (! $user) {
                $this->markLegacyTransactionFailed((int) $legacyTransaction->id, 'Unable to resolve the owning user for this legacy transaction.');
                $this->warn("Skipped legacy transaction {$legacyTransaction->id}: missing user.");

                continue;
            }

            $prompt = $promptBuilder->build($legacyTransaction);

            try {
                Auth::setUser($user);

                $transaction = $ledgerMigrator->migrate($user, $legacyTransaction);

                DB::table('legacy_transactions')
                    ->where('id', $legacyTransaction->id)
                    ->update([
                        'is_migrated' => true,
                        'migration_error' => null,
                        'updated_at' => now(),
                    ]);

                $this->info("Migrated legacy transaction {$legacyTransaction->id} into ledger transaction {$transaction->id}.");
            } catch (Throwable $exception) {
                $this->markLegacyTransactionFailed(
                    (int) $legacyTransaction->id,
                    $exception->getMessage() . " | Prompt: {$prompt}",
                );
                $this->error("Failed to migrate legacy transaction {$legacyTransaction->id}: {$exception->getMessage()}");
            } finally {
                Auth::forgetGuards();
            }
        }

        return self::SUCCESS;
    }

    private function markLegacyTransactionFailed(int $legacyTransactionId, string $message): void
    {
        DB::table('legacy_transactions')
            ->where('id', $legacyTransactionId)
            ->update([
                'migration_error' => mb_substr($message, 0, 65535),
                'updated_at' => now(),
            ]);
    }
}
