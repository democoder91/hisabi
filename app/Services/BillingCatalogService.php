<?php

namespace App\Services;

use App\Models\BillingProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingCatalogService
{
    public function billingCurrency(): string
    {
        $product = BillingProduct::query()
            ->active()
            ->orderBy('sort_order')
            ->first();

        return $product ? $product->currency : (string) config('billing.currency', 'USD');
    }

    public function creditPackages(): Collection
    {
        return $this->productsByType(BillingProduct::TYPE_CREDITS);
    }

    public function subscriptionPlans(): Collection
    {
        return $this->productsByType(BillingProduct::TYPE_SUBSCRIPTION);
    }

    public function findProduct(string $type, string $slug): ?BillingProduct
    {
        return BillingProduct::query()
            ->active()
            ->forType($type)
            ->where('slug', $slug)
            ->first();
    }

    public function publicPayload(): array
    {
        return [
            'creditPackages' => $this->creditPackages()->map(fn (BillingProduct $product): array => $this->serializePublicProduct($product))->all(),
            'subscriptionPlans' => $this->subscriptionPlans()->map(fn (BillingProduct $product): array => $this->serializePublicProduct($product))->all(),
            'billingCurrency' => $this->billingCurrency(),
        ];
    }

    public function managementPayload(): array
    {
        return [
            'creditPackages' => $this->creditPackages()->map(fn (BillingProduct $product): array => $this->serializeManagementProduct($product))->all(),
            'subscriptionPlans' => $this->subscriptionPlans()->map(fn (BillingProduct $product): array => $this->serializeManagementProduct($product))->all(),
            'billingCurrency' => $this->billingCurrency(),
        ];
    }

    public function updateCatalog(string $currency, array $creditPackages, array $subscriptionPlans): void
    {
        DB::transaction(function () use ($currency, $creditPackages, $subscriptionPlans): void {
            $this->syncProducts(BillingProduct::TYPE_CREDITS, $currency, $creditPackages);
            $this->syncProducts(BillingProduct::TYPE_SUBSCRIPTION, $currency, $subscriptionPlans);
        });
    }

    private function productsByType(string $type): Collection
    {
        return BillingProduct::query()
            ->active()
            ->forType($type)
            ->orderBy('sort_order')
            ->get();
    }

    private function serializePublicProduct(BillingProduct $product): array
    {
        return [
            'slug' => $product->slug,
            'name' => $product->localizedName(),
            'currency' => $product->currency,
            'price' => $product->price,
            'credits' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
        ];
    }

    private function serializeManagementProduct(BillingProduct $product): array
    {
        return [
            'slug' => $product->slug,
            'name_en' => $product->name_en ?: $product->name,
            'name_ar' => $product->name_ar ?: $product->name,
            'currency' => $product->currency,
            'price' => $product->price,
            'credits' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
        ];
    }

    private function syncProducts(string $type, string $currency, array $products): void
    {
        $slugs = [];

        foreach (array_values($products) as $index => $product) {
            $slugs[] = $product['slug'];

            BillingProduct::query()->updateOrCreate(
                [
                    'type' => $type,
                    'slug' => $product['slug'],
                ],
                [
                    'name' => $product['name_en'],
                    'name_en' => $product['name_en'],
                    'name_ar' => $product['name_ar'],
                    'currency' => $currency,
                    'price' => (int) $product['price'],
                    'price_cents' => (int) $product['price'] * 100,
                    'credits' => (int) $product['credits'],
                    'renews_in_days' => $type === BillingProduct::TYPE_SUBSCRIPTION ? (int) $product['renews_in_days'] : null,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        BillingProduct::query()
            ->forType($type)
            ->whereNotIn('slug', $slugs)
            ->update(['is_active' => false]);
    }
}
