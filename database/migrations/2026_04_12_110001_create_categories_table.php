<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->json('name');
            $table->string('type');
            $table->string('color')->default('gray');
            $table->string('icon')->default('wallet');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('account_id', 'categories_account_id_unique');
            $table->index(['user_id', 'type'], 'categories_user_id_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};