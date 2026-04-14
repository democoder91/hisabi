<?php

namespace App\Services;

use App\Models\BillingGrantAudit;
use App\Models\BillingProduct;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingGrantService
{
    public function grantCatalogProduct(User $adminUser, User $targetUser, BillingProduct $product): BillingGrantAudit
    {
        return DB::transaction(function () use ($adminUser, $targetUser, $product): BillingGrantAudit {
            $targetUser->load('subscriptions');

            $oldValues = $this->snapshotUserState($targetUser);

            $this->applyGrant($targetUser, [
                'type' => $product->type,
                'product_name' => $product->localizedName($targetUser->locale),
                'credits' => (int) $product->credits,
                'renews_in_days' => (int) ($product->renews_in_days ?? 0),
                'paymob_order_id' => null,
            ]);

            $freshUser = $targetUser->fresh(['subscriptions']);

            return BillingGrantAudit::query()->create([
                'admin_user_id' => $adminUser->id,
                'target_user_id' => $targetUser->id,
                'billing_product_id' => $product->id,
                'grant_type' => $product->type,
                'product_snapshot' => $this->productSnapshot($product),
                'old_values' => $oldValues,
                'new_values' => $freshUser ? $this->snapshotUserState($freshUser) : null,
            ]);
        });
    }

    public function applySuccessfulPaymentTransaction(PaymentTransaction $paymentTransaction): void
    {
        DB::transaction(function () use ($paymentTransaction): void {
            $paymentTransaction->refresh();

            if ($paymentTransaction->status === 'success') {
                return;
            }

            $paymentTransaction->update(['status' => 'success']);

            /** @var User|null $targetUser */
            $targetUser = $paymentTransaction->user()->first();

            if (! $targetUser) {
                return;
            }

            $this->applyGrant($targetUser, [
                'type' => (string) $paymentTransaction->type,
                'product_name' => (string) ($paymentTransaction->product_name ?: 'Billing product'),
                'credits' => (int) ($paymentTransaction->credits_added ?? 0),
                'renews_in_days' => (int) ($paymentTransaction->renews_in_days ?? 0),
                'paymob_order_id' => $paymentTransaction->paymob_order_id,
            ]);
        });
    }

    private function applyGrant(User $targetUser, array $grant): void
    {
        $credits = max(0, (int) ($grant['credits'] ?? 0));
        $grantType = (string) ($grant['type'] ?? '');

        if ($credits > 0) {
            $targetUser->available_credits = (int) $targetUser->available_credits + $credits;
        }

        if ($grantType === BillingProduct::TYPE_SUBSCRIPTION) {
            $targetUser->trial_ends_at = null;
        }

        if ($credits > 0 || $grantType === BillingProduct::TYPE_SUBSCRIPTION) {
            $targetUser->save();
        }

        if ($grantType === BillingProduct::TYPE_SUBSCRIPTION) {
            $this->activateSubscription($targetUser, $grant);
        }
    }

    private function activateSubscription(User $targetUser, array $grant): void
    {
        $currentSubscription = Subscription::query()
            ->where('user_id', $targetUser->id)
            ->first();

        $renewsInDays = max(1, (int) ($grant['renews_in_days'] ?? 30));
        $renewalBase = $this->renewalBase($currentSubscription);

        Subscription::query()->updateOrCreate(
            ['user_id' => $targetUser->id],
            [
                'plan_name' => (string) ($grant['product_name'] ?? 'Subscription'),
                'status' => 'active',
                'paymob_order_id' => $grant['paymob_order_id'],
                'renews_at' => $renewalBase->addDays($renewsInDays),
            ],
        );
    }

    private function renewalBase(?Subscription $subscription): Carbon
    {
        if (! $subscription) {
            return now();
        }

        if ($subscription->status === 'active' && $subscription->renews_at && $subscription->renews_at->isFuture()) {
            return $subscription->renews_at->copy();
        }

        return now();
    }

    private function snapshotUserState(User $user): array
    {
        $subscription = $user->subscriptions
            ->sortByDesc(fn (Subscription $item): int => $item->renews_at ? (int) $item->renews_at->getTimestamp() : 0)
            ->first();

        return [
            'available_credits' => (int) $user->available_credits,
            'trial_ends_at' => $user->trial_ends_at ? $user->trial_ends_at->toISOString() : null,
            'subscription' => $this->subscriptionSnapshot($subscription),
        ];
    }

    private function subscriptionSnapshot(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'plan_name' => $subscription->plan_name,
            'status' => $subscription->status,
            'renews_at' => $subscription->renews_at ? $subscription->renews_at->toISOString() : null,
        ];
    }

    private function productSnapshot(BillingProduct $product): array
    {
        return [
            'id' => $product->id,
            'type' => $product->type,
            'slug' => $product->slug,
            'name_en' => $product->name_en,
            'name_ar' => $product->name_ar,
            'currency' => $product->currency,
            'price' => $product->price,
            'credits' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
        ];
    }
}
