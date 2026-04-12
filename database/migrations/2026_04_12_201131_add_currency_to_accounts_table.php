<?php

use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default(Currency::default()->value)->after('balance');
        });

        $users = DB::table('users')->select(['id', 'default_currency'])->get()->keyBy('id');

        DB::table('accounts')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use ($users) {
                foreach ($accounts as $account) {
                    $defaultCurrency = $users->get($account->user_id)?->default_currency ?: config('hisabi.currency');

                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update(['currency' => $defaultCurrency]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
