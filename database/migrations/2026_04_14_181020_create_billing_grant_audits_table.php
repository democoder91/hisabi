<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_grant_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('billing_product_id')->nullable()->constrained('billing_products')->nullOnDelete();
            $table->string('grant_type')->index();
            $table->json('product_snapshot');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'id']);
            $table->index(['admin_user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_grant_audits');
    }
};
