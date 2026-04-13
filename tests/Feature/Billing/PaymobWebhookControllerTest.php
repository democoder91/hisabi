<?php

use App\Models\BillingProduct;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paymob.hmac_secret', 'super-secret-key');

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

it('rejects a paymob webhook with an invalid hmac', function () {
    /** @var User $user */
    $user = User::factory()->create();

    PaymentTransaction::query()->create([
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

    $payload = paymobTransactionPayload(5001);

    $response = postJson('/api/paymob/webhook?hmac=invalid-signature', $payload);

    $response->assertForbidden();

    expect($user->fresh()->available_credits)->toBe(0);
    assertDatabaseHas('payment_transactions', [
        'paymob_order_id' => 5001,
        'status' => 'pending',
    ]);
});

it('accepts a valid paymob webhook and adds credits once', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'available_credits' => 5,
    ]);

    PaymentTransaction::query()->create([
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

    $payload = paymobTransactionPayload(5001);
    $hmac = app(PaymobService::class)->generateTransactionCallbackHmac($payload);

    $response = postJson('/api/paymob/webhook?hmac=' . $hmac, $payload);

    $response->assertOk()
        ->assertJson(['status' => 'processed']);

    expect($user->fresh()->available_credits)->toBe(105);
    assertDatabaseHas('payment_transactions', [
        'paymob_order_id' => 5001,
        'status' => 'success',
    ]);
});

it('activates a subscription and clears the trial on a valid subscription webhook', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(10),
    ]);

    PaymentTransaction::query()->create([
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

    $payload = paymobTransactionPayload(7001, 2900);
    $hmac = app(PaymobService::class)->generateTransactionCallbackHmac($payload);

    $response = postJson('/api/paymob/webhook?hmac=' . $hmac, $payload);

    $response->assertOk();

    assertDatabaseHas('subscriptions', [
        'user_id' => $user->id,
        'plan_name' => 'Pro Monthly',
        'status' => 'active',
        'paymob_order_id' => 7001,
    ]);
    expect($user->fresh()->available_credits)->toBe(435);
    expect($user->fresh()->trial_ends_at)->toBeNull();
});

function paymobTransactionPayload(int $orderId, int $amountCents = 1000): array
{
    return [
        'type' => 'TRANSACTION',
        'obj' => [
            'id' => 9001,
            'pending' => false,
            'amount_cents' => $amountCents,
            'success' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'is_refunded' => false,
            'is_3d_secure' => true,
            'integration_id' => 98765,
            'has_parent_transaction' => false,
            'order' => [
                'id' => $orderId,
            ],
            'created_at' => '2026-04-13T10:00:00+00:00',
            'currency' => 'USD',
            'source_data' => [
                'pan' => '2346',
                'sub_type' => 'MasterCard',
                'type' => 'card',
            ],
            'error_occured' => false,
            'owner' => 42,
        ],
    ];
}
