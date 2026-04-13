<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_products', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('name_ar')->nullable()->after('name_en');
            $table->unsignedInteger('price')->nullable()->after('price_cents');
        });

        DB::table('billing_products')
            ->select(['id', 'name', 'price_cents'])
            ->orderBy('id')
            ->get()
            ->each(function (object $product): void {
                DB::table('billing_products')
                    ->where('id', $product->id)
                    ->update([
                        'name_en' => $product->name,
                        'name_ar' => $product->name,
                        'price' => max(1, (int) round(((int) $product->price_cents) / 100)),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('billing_products', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ar', 'price']);
        });
    }
};
