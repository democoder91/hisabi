<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('product_slug')->nullable()->after('paymob_order_id');
            $table->string('product_name')->nullable()->after('product_slug');
            $table->string('currency', 3)->nullable()->after('amount');
            $table->unsignedInteger('renews_in_days')->nullable()->after('credits_added');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['product_slug', 'product_name', 'currency', 'renews_in_days']);
        });
    }
};
