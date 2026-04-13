<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_products', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('currency', 3);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedInteger('renews_in_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_products');
    }

    private function seedCatalog(): void
    {
        $creditPackages = (array) config('billing.credit_packages', []);
        $subscriptionPlans = (array) config('billing.subscription_plans', []);
        $currency = (string) config('billing.currency', 'USD');
        $timestamp = now();
        $records = [];
        $basePackage = reset($creditPackages) ?: ['credits' => 1, 'amount_cents' => 1];
        $baseCredits = max(1, (int) ($basePackage['credits'] ?? 1));
        $basePriceCents = max(1, (int) ($basePackage['amount_cents'] ?? 1));

        foreach (array_values($creditPackages) as $index => $package) {
            $slug = array_keys($creditPackages)[$index];

            $records[] = [
                'type' => 'credits',
                'slug' => $slug,
                'name' => (string) ($package['name'] ?? 'Credits'),
                'currency' => $currency,
                'price_cents' => max(1, (int) ($package['amount_cents'] ?? 1)),
                'credits' => max(1, (int) ($package['credits'] ?? 1)),
                'renews_in_days' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_values($subscriptionPlans) as $index => $plan) {
            $slug = array_keys($subscriptionPlans)[$index];
            $priceCents = max(1, (int) ($plan['amount_cents'] ?? 1));

            $records[] = [
                'type' => 'subscription',
                'slug' => $slug,
                'name' => (string) ($plan['name'] ?? 'Subscription'),
                'currency' => $currency,
                'price_cents' => $priceCents,
                'credits' => max(1, (int) round(($priceCents * $baseCredits * 1.5) / $basePriceCents)),
                'renews_in_days' => max(1, (int) ($plan['renews_in_days'] ?? 30)),
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($records !== []) {
            DB::table('billing_products')->insert($records);
        }
    }
};
