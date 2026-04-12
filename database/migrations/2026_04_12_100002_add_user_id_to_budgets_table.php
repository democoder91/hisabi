<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $primaryUserId = DB::table('users')->orderBy('id')->value('id');

        if ($primaryUserId === null && DB::table('budgets')->exists()) {
            throw new \RuntimeException('Unable to backfill budgets.user_id because no users exist.');
        }

        if ($primaryUserId !== null) {
            DB::table('budgets')->whereNull('user_id')->update(['user_id' => $primaryUserId]);
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};