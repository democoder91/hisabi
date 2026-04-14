<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\StartCreditCheckoutRequest;
use App\Http\Requests\Billing\StartSubscriptionCheckoutRequest;
use App\Models\BillingProduct;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\BillingCatalogService;
use App\Services\PaymobService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class CheckoutController extends Controller
{
    private BillingCatalogService $billingCatalogService;
    private PaymobService $paymobService;

    public function __construct(BillingCatalogService $billingCatalogService, PaymobService $paymobService)
    {
        $this->billingCatalogService = $billingCatalogService;
        $this->paymobService = $paymobService;
    }

    public function buyCredits(StartCreditCheckoutRequest $request, string $package): RedirectResponse
    {
        $product = $this->billingCatalogService->findProduct(BillingProduct::TYPE_CREDITS, $package);

        abort_unless($product, 404);

        /** @var User $user */
        $user = $request->user();

        return $this->startCheckout($user, $product);
    }

    public function subscribe(StartSubscriptionCheckoutRequest $request, string $plan): RedirectResponse
    {
        $product = $this->billingCatalogService->findProduct(BillingProduct::TYPE_SUBSCRIPTION, $plan);

        abort_unless($product, 404);

        /** @var User $user */
        $user = $request->user();

        return $this->startCheckout($user, $product);
    }

    private function startCheckout(User $user, BillingProduct $product): RedirectResponse
    {
        if (! $this->billingCatalogService->checkoutAvailable()) {
            return redirect()->route('billing.index');
        }

        $integrationId = (int) config('paymob.integration_id_card');

        if ($integrationId < 1) {
            throw new RuntimeException('Paymob integration is not configured.');
        }

        $productName = $product->localizedName($user->locale);
        $amountCents = $product->paymobAmountCents();
        $authToken = $this->paymobService->authenticate();
        $order = $this->paymobService->registerOrder($authToken, $amountCents, [[
            'name' => $productName,
            'amount_cents' => $amountCents,
            'quantity' => 1,
            'description' => $productName,
        ]], $product->currency);

        $orderId = (int) ($order['id'] ?? 0);

        if ($orderId < 1) {
            throw new RuntimeException('Paymob order registration did not return an order id.');
        }

        PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'paymob_order_id' => $orderId,
            'product_slug' => $product->slug,
            'product_name' => $productName,
            'amount' => $amountCents,
            'currency' => $product->currency,
            'type' => $product->type,
            'credits_added' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
            'status' => 'pending',
        ]);

        $paymentKey = $this->paymobService->getPaymentKey(
            $authToken,
            $amountCents,
            $orderId,
            $this->billingData($user),
            $product->currency,
            $integrationId,
        );

        return redirect()->away($this->paymobService->generateIframeUrl($paymentKey));
    }

    private function billingData(User $user): array
    {
        [$firstName, $lastName] = $this->splitName($user->name);

        return [
            'apartment' => 'NA',
            'email' => $user->email,
            'floor' => 'NA',
            'first_name' => $firstName,
            'street' => 'Billing Street',
            'building' => 'NA',
            'phone_number' => '+201000000000',
            'shipping_method' => 'PKG',
            'postal_code' => '00000',
            'city' => 'Cairo',
            'country' => 'EG',
            'last_name' => $lastName,
            'state' => 'Cairo',
        ];
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = $parts[0] ?? 'Customer';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';

        return [$firstName, $lastName];
    }
}
