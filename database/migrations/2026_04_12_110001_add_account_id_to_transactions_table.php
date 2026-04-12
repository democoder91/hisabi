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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $primaryUserId = DB::table('users')->orderBy('id')->value('id');

        if ($primaryUserId === null && DB::table('transactions')->exists()) {
            throw new \RuntimeException('Unable to backfill transactions.account_id because no users exist.');
        }

        if ($primaryUserId !== null) {
            $defaultAccountId = DB::table('accounts')
                ->where('user_id', $primaryUserId)
                ->where('name', Account::DEFAULT_NAME)
                ->value('id');

            if ($defaultAccountId === null) {
                $defaultAccountId = DB::table('accounts')->insertGetId([
                    'user_id' => $primaryUserId,
                    'name' => Account::DEFAULT_NAME,
                    'balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('transactions')->whereNull('account_id')->update(['account_id' => $defaultAccountId]);

            $balance = Transaction::query()
                ->withoutGlobalScopes()
                ->where('account_id', $defaultAccountId)
                ->get()
                ->sum(fn (Transaction $transaction) => $transaction->signedAmount());

            DB::table('accounts')->where('id', $defaultAccountId)->update([
                'balance' => round($balance, 2),
                'updated_at' => now(),
            ]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};