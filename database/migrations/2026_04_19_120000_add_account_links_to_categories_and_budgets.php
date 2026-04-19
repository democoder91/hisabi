<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();

            $table->unique('account_id', 'categories_account_id_unique');
        });

        Schema::create('budget_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['budget_id', 'account_id'], 'budget_account_budget_id_account_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_account');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_account_id_unique');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
