<?php

use App\Models\BillingProduct;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paymob.api_key', 'paymob-api-key');
    config()->set('paymob.integration_id_card', '98765');
    config()->set('paymob.iframe_id', '12345');
    config()->set('paymob.base_url', 'https://accept.paymob.com');

    BillingProduct::query()->where('slug', 'starter-100')->update([
        'currency' => 'USD',
        'name_en' => '100 Credits',
        'name_ar' => '100 رصيد',
        'price' => 10,
        'price_cents' => 1000,
        'credits' => 100,
    ]);

    BillingProduct::query()->where('slug', 'pro-monthly')->update([
        'currency' => 'USD',
        'name_en' => 'Pro Monthly',
        'name_ar' => 'برو شهري',
        'price' => 29,
        'price_cents' => 2900,
        'credits' => 435,
        'renews_in_days' => 30,
    ]);
});

it('creates a pending credit transaction and redirects to paymob', function () {
    /** @var User $user */
    $user = User::factory()->create();

    Http::fake([
        'https://accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'auth-token']),
        'https://accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 5001]),
        'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'payment-key']),
    ]);

    actingAs($user);

    $response = post(route('billing.checkout.credits', 'starter-100'));

    $response->assertRedirect('https://accept.paymob.com/api/acceptance/iframes/12345?payment_token=payment-key');

    assertDatabaseHas('payment_transactions', [
        'user_id' => $user->id,
        'paymob_order_id' => 5001,
        'product_slug' => 'starter-100',
        'product_name' => '100 Credits',
        'amount' => 1000,
        'currency' => 'USD',
        'type' => 'credits',
        'credits_added' => 100,
        'status' => 'pending',
    ]);
});

it('creates a pending subscription transaction and redirects to paymob', function () {
    /** @var User $user */
    $user = User::factory()->create();

    Http::fake([
        'https://accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'auth-token']),
        'https://accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 7001]),
        'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'subscription-key']),
    ]);

    actingAs($user);

    $response = post(route('billing.checkout.subscription', 'pro-monthly'));

    $response->assertRedirect('https://accept.paymob.com/api/acceptance/iframes/12345?payment_token=subscription-key');

    assertDatabaseHas('payment_transactions', [
        'user_id' => $user->id,
        'paymob_order_id' => 7001,
        'product_slug' => 'pro-monthly',
        'product_name' => 'Pro Monthly',
        'amount' => 2900,
        'currency' => 'USD',
        'type' => 'subscription',
        'credits_added' => 435,
        'renews_in_days' => 30,
        'status' => 'pending',
    ]);
});

it('uses a credit package created through billing management in checkout', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($superUser);

    post(route('billing.manage.credit-packages.store'), [
        'currency' => 'USD',
        'slug' => 'enterprise-1500',
        'name_en' => 'Enterprise Credits',
        'name_ar' => 'أرصدة المؤسسات',
        'price' => 150,
        'credits' => 1500,
    ])->assertRedirect(route('billing.manage'));

    /** @var User $buyer */
    $buyer = User::factory()->create();

    Http::fake([
        'https://accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'auth-token']),
        'https://accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 9001]),
        'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'enterprise-key']),
    ]);

    actingAs($buyer);

    $response = post(route('billing.checkout.credits', 'enterprise-1500'));

    $response->assertRedirect('https://accept.paymob.com/api/acceptance/iframes/12345?payment_token=enterprise-key');

    assertDatabaseHas('payment_transactions', [
        'user_id' => $buyer->id,
        'paymob_order_id' => 9001,
        'product_slug' => 'enterprise-1500',
        'product_name' => 'Enterprise Credits',
        'amount' => 15000,
        'currency' => 'USD',
        'type' => 'credits',
        'credits_added' => 1500,
        'status' => 'pending',
    ]);
});

it('redirects back to billing when checkout is unavailable', function () {
    config()->set('paymob.api_key', null);
    config()->set('paymob.integration_id_card', null);
    config()->set('paymob.iframe_id', null);

    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    post(route('billing.checkout.credits', 'starter-100'))
        ->assertRedirect(route('billing.index'));

    expect(PaymentTransaction::query()->count())->toBe(0);
});
