<?php

namespace App\Services;

use App\Models\BillingProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            'checkoutAvailable' => $this->checkoutAvailable(),
        ];
    }

    public function checkoutAvailable(): bool
    {
        return (string) config('paymob.api_key', '') !== ''
            && (int) config('paymob.integration_id_card', 0) > 0
            && (string) config('paymob.iframe_id', '') !== '';
    }

    public function managementPayload(): array
    {
        return [
            'creditPackages' => $this->creditPackages()->map(fn (BillingProduct $product): array => $this->serializeManagementProduct($product))->all(),
            'subscriptionPlans' => $this->subscriptionPlans()->map(fn (BillingProduct $product): array => $this->serializeManagementProduct($product))->all(),
            'billingCurrency' => $this->billingCurrency(),
        ];
    }

    public function updateCurrency(string $currency): void
    {
        BillingProduct::query()->update([
            'currency' => $currency,
        ]);
    }

    public function createCreditPackage(array $attributes): BillingProduct
    {
        return $this->createProduct(BillingProduct::TYPE_CREDITS, $attributes);
    }

    public function updateCreditPackage(BillingProduct $product, array $attributes): BillingProduct
    {
        return $this->updateProduct($product, BillingProduct::TYPE_CREDITS, $attributes);
    }

    public function deleteCreditPackage(BillingProduct $product): void
    {
        $this->deleteProduct($product, BillingProduct::TYPE_CREDITS);
    }

    public function reorderCreditPackages(array $productIds): void
    {
        $this->reorderProducts(BillingProduct::TYPE_CREDITS, $productIds);
    }

    public function createSubscriptionPlan(array $attributes): BillingProduct
    {
        return $this->createProduct(BillingProduct::TYPE_SUBSCRIPTION, $attributes);
    }

    public function updateSubscriptionPlan(BillingProduct $product, array $attributes): BillingProduct
    {
        return $this->updateProduct($product, BillingProduct::TYPE_SUBSCRIPTION, $attributes);
    }

    public function deleteSubscriptionPlan(BillingProduct $product): void
    {
        $this->deleteProduct($product, BillingProduct::TYPE_SUBSCRIPTION);
    }

    public function reorderSubscriptionPlans(array $productIds): void
    {
        $this->reorderProducts(BillingProduct::TYPE_SUBSCRIPTION, $productIds);
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
            'id' => $product->getKey(),
            'slug' => $product->slug,
            'name_en' => $product->name_en ?: $product->name,
            'name_ar' => $product->name_ar ?: $product->name,
            'currency' => $product->currency,
            'price' => $product->price,
            'credits' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
        ];
    }

    private function createProduct(string $type, array $attributes): BillingProduct
    {
        return DB::transaction(function () use ($type, $attributes): BillingProduct {
            $this->updateCurrency($attributes['currency']);

            return BillingProduct::query()->create($this->productPayload(
                $type,
                $attributes,
                ((int) BillingProduct::query()->forType($type)->max('sort_order')) + 1,
            ));
        });
    }

    private function updateProduct(BillingProduct $product, string $type, array $attributes): BillingProduct
    {
        $this->guardType($product, $type);

        return DB::transaction(function () use ($product, $type, $attributes): BillingProduct {
            $this->updateCurrency($attributes['currency']);

            $product->fill($this->productPayload($type, $attributes, $product->sort_order));
            $product->save();

            return $product->refresh();
        });
    }

    private function deleteProduct(BillingProduct $product, string $type): void
    {
        $this->guardType($product, $type);

        DB::transaction(function () use ($product, $type): void {
            $product->delete();
            $this->resequenceProducts($type);
        });
    }

    private function reorderProducts(string $type, array $productIds): void
    {
        $normalizedProductIds = array_values(array_map('intval', $productIds));
        $activeProductIds = BillingProduct::query()
            ->active()
            ->forType($type)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        $expectedProductIds = $activeProductIds;
        $receivedProductIds = $normalizedProductIds;

        sort($expectedProductIds);
        sort($receivedProductIds);

        if ($expectedProductIds !== $receivedProductIds) {
            throw ValidationException::withMessages([
                'product_ids' => 'The provided billing products do not match the current catalog.',
            ]);
        }

        DB::transaction(function () use ($type, $normalizedProductIds): void {
            foreach ($normalizedProductIds as $index => $productId) {
                BillingProduct::query()
                    ->forType($type)
                    ->whereKey($productId)
                    ->update([
                        'sort_order' => $index + 1,
                    ]);
            }
        });
    }

    private function productPayload(string $type, array $attributes, int $sortOrder): array
    {
        return [
            'type' => $type,
            'slug' => $attributes['slug'],
            'name' => $attributes['name_en'],
            'name_en' => $attributes['name_en'],
            'name_ar' => $attributes['name_ar'],
            'currency' => $attributes['currency'],
            'price' => (int) $attributes['price'],
            'price_cents' => (int) $attributes['price'] * 100,
            'credits' => (int) $attributes['credits'],
            'renews_in_days' => $type === BillingProduct::TYPE_SUBSCRIPTION ? (int) $attributes['renews_in_days'] : null,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];
    }

    private function resequenceProducts(string $type): void
    {
        BillingProduct::query()
            ->forType($type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (BillingProduct $product, int $index): void {
                $product->update([
                    'sort_order' => $index + 1,
                ]);
            });
    }

    private function guardType(BillingProduct $product, string $expectedType): void
    {
        if ($product->type !== $expectedType) {
            throw new NotFoundHttpException();
        }
    }
}
