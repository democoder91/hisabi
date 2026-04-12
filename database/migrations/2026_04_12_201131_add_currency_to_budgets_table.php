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
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('currency', 3)->default(Currency::default()->value)->after('amount');
        });

        $users = DB::table('users')->select(['id', 'default_currency'])->get()->keyBy('id');

        DB::table('budgets')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(100, function ($budgets) use ($users) {
                foreach ($budgets as $budget) {
                    $defaultCurrency = $users->get($budget->user_id)?->default_currency ?: config('hisabi.currency');

                    DB::table('budgets')
                        ->where('id', $budget->id)
                        ->update(['currency' => $defaultCurrency]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
