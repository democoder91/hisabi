<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default(config('hisabi.currency'));
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('saving')->default(false);
            $table->unsignedInteger('period')->default(1);
            $table->string('reoccurrence');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'reoccurrence'], 'budgets_user_id_reoccurrence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};