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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('rate', 18, 8)->default(1);
            $table->string('source', 20)->default('default');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        $users = DB::table('users')->select('id')->get();
        $currencies = Currency::values();

        if ($users->isEmpty()) {
            return;
        }

        $timestamp = now();
        $rows = [];

        foreach ($users as $user) {
            foreach ($currencies as $currency) {
                $rows[] = [
                    'user_id' => $user->id,
                    'currency' => $currency,
                    'rate' => 1,
                    'source' => 'default',
                    'last_synced_at' => $currency === Currency::USD->value ? $timestamp : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('exchange_rates')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
