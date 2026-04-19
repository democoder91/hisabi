<?php

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::rename('account_user', 'legacy_account_user');
        Schema::rename('transaction_audits', 'legacy_transaction_audits');
        Schema::rename('transactions', 'legacy_transactions');
        Schema::rename('accounts', 'legacy_accounts');

        Schema::enableForeignKeyConstraints();

        Schema::create('legacy_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('type');
            $table->string('color')->default('gray');
            $table->string('icon')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $categoryRows = DB::table('categories')->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();

        if ($categoryRows !== []) {
            DB::table('legacy_categories')->insert($categoryRows);
        }

        Schema::table('legacy_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->boolean('is_migrated')->default(false)->after('deleted_at');
            $table->text('migration_error')->nullable()->after('is_migrated');
        });

        DB::table('legacy_transactions')->update([
            'user_id' => DB::raw('(select legacy_accounts.user_id from legacy_accounts where legacy_accounts.id = legacy_transactions.account_id limit 1)'),
        ]);

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->enum('type', Account::ledgerTypes())->default(Account::TYPE_ASSET);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 3)->default(config('hisabi.currency'));
            $table->string('color', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission_level');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'user_id'], 'ledger_account_user_account_id_user_id_unique');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default(config('hisabi.currency'));
            $table->string('transaction_type', 10)->default(Transaction::TYPE_DEBIT);
            $table->text('note')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('date')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'ledger_transactions_user_id_created_at_index');
            $table->index(['from_account_id', 'to_account_id'], 'ledger_transactions_from_to_index');
        });

        Schema::create('transaction_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index('transaction_id', 'ledger_transaction_audits_transaction_id_index');
            $table->index(['account_id', 'id'], 'ledger_transaction_audits_account_id_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_audits');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');

        Schema::table('legacy_transactions', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'is_migrated', 'migration_error']);
        });

        Schema::dropIfExists('legacy_categories');

        Schema::disableForeignKeyConstraints();

        Schema::rename('legacy_accounts', 'accounts');
        Schema::rename('legacy_transactions', 'transactions');
        Schema::rename('legacy_transaction_audits', 'transaction_audits');
        Schema::rename('legacy_account_user', 'account_user');

        Schema::enableForeignKeyConstraints();
    }
};