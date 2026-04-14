<?php

use App\Models\BillingProduct;
use App\Models\User;
use App\Services\BillingCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

it('allows super users to open the billing management panel', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($user);

    get(route('billing.manage'))->assertOk();
});

it('forbids non super users from opening the billing management panel', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => false,
    ]);

    actingAs($user);

    get(route('billing.manage'))->assertForbidden();
});

it('forbids non super users from mutating billing products', function () {
    $creditPackage = BillingProduct::query()->create([
        'type' => 'credits',
        'slug' => 'starter-100-blocked',
        'name' => 'Blocked Starter Top Up',
        'name_en' => 'Blocked Starter Top Up',
        'name_ar' => 'شحن البداية المحظور',
        'currency' => 'EGP',
        'price_cents' => 1000,
        'price' => 10,
        'credits' => 100,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => false,
    ]);

    actingAs($user);

    post(route('billing.manage.credit-packages.store'), [
        'currency' => 'EGP',
        'slug' => 'starter-100-denied',
        'name_en' => 'Starter Top Up',
        'name_ar' => 'شحن البداية',
        'price' => 10,
        'credits' => 100,
    ])->assertForbidden();

    putJson(route('billing.manage.credit-packages.reorder'), [
        'product_ids' => [$creditPackage->id],
    ])->assertForbidden();
});

it('allows super users to create and update credit packages', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($user);

    $createResponse = post(route('billing.manage.credit-packages.store'), [
        'currency' => 'EGP',
        'slug' => 'team-900',
        'name_en' => 'Team Top Up',
        'name_ar' => 'شحن الفريق',
        'price' => 90,
        'credits' => 900,
    ]);

    $createResponse->assertRedirect(route('billing.manage'));

    $creditPackage = BillingProduct::query()->where('slug', 'team-900')->firstOrFail();

    assertDatabaseHas('billing_products', [
        'id' => $creditPackage->id,
        'slug' => 'team-900',
        'type' => 'credits',
        'name' => 'Team Top Up',
        'name_en' => 'Team Top Up',
        'name_ar' => 'شحن الفريق',
        'currency' => 'EGP',
        'price' => 90,
        'price_cents' => 9000,
        'credits' => 900,
    ]);

    $updateResponse = put(route('billing.manage.credit-packages.update', $creditPackage), [
        'currency' => 'USD',
        'slug' => 'team-1000',
        'name_en' => 'Team Top Up Plus',
        'name_ar' => 'شحن الفريق بلس',
        'price' => 100,
        'credits' => 1000,
    ]);

    $updateResponse->assertRedirect(route('billing.manage'));

    assertDatabaseHas('billing_products', [
        'id' => $creditPackage->id,
        'slug' => 'team-1000',
        'type' => 'credits',
        'currency' => 'USD',
        'name' => 'Team Top Up Plus',
        'name_en' => 'Team Top Up Plus',
        'name_ar' => 'شحن الفريق بلس',
        'price' => 100,
        'price_cents' => 10000,
        'credits' => 1000,
    ]);
});

it('allows super users to create, update, and delete subscription plans', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($user);

    $createResponse = post(route('billing.manage.subscription-plans.store'), [
        'currency' => 'EGP',
        'slug' => 'business-monthly',
        'name_en' => 'Business Monthly',
        'name_ar' => 'الأعمال الشهري',
        'price' => 150,
        'credits' => 2500,
        'renews_in_days' => 30,
    ]);

    $createResponse->assertRedirect(route('billing.manage'));

    $subscriptionPlan = BillingProduct::query()->where('slug', 'business-monthly')->firstOrFail();

    $updateResponse = put(route('billing.manage.subscription-plans.update', $subscriptionPlan), [
        'currency' => 'USD',
        'slug' => 'business-quarterly',
        'name_en' => 'Business Quarterly',
        'name_ar' => 'الأعمال الربع سنوي',
        'price' => 420,
        'credits' => 9000,
        'renews_in_days' => 90,
    ]);

    $updateResponse->assertRedirect(route('billing.manage'));

    assertDatabaseHas('billing_products', [
        'id' => $subscriptionPlan->id,
        'slug' => 'business-quarterly',
        'type' => 'subscription',
        'name' => 'Business Quarterly',
        'name_en' => 'Business Quarterly',
        'name_ar' => 'الأعمال الربع سنوي',
        'currency' => 'USD',
        'price' => 420,
        'price_cents' => 42000,
        'credits' => 9000,
        'renews_in_days' => 90,
    ]);

    $deleteResponse = delete(route('billing.manage.subscription-plans.destroy', $subscriptionPlan));

    $deleteResponse->assertRedirect(route('billing.manage'));

    assertDatabaseMissing('billing_products', [
        'id' => $subscriptionPlan->id,
    ]);
});

it('updates the shared billing currency across subscriptions and top ups', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($user);

    $creditPackage = BillingProduct::query()->create([
        'type' => 'credits',
        'slug' => 'credit-sync-test',
        'name' => 'Credit Sync Test',
        'name_en' => 'Credit Sync Test',
        'name_ar' => 'اختبار مزامنة الشحن',
        'currency' => 'EGP',
        'price_cents' => 1000,
        'price' => 10,
        'credits' => 100,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $subscriptionPlan = BillingProduct::query()->create([
        'type' => 'subscription',
        'slug' => 'subscription-sync-test',
        'name' => 'Subscription Sync Test',
        'name_en' => 'Subscription Sync Test',
        'name_ar' => 'اختبار مزامنة الاشتراك',
        'currency' => 'EGP',
        'price_cents' => 5000,
        'price' => 50,
        'credits' => 600,
        'renews_in_days' => 30,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = put(route('billing.manage.currency.update'), [
        'currency' => 'USD',
    ]);

    $response->assertRedirect(route('billing.manage'));

    assertDatabaseHas('billing_products', [
        'id' => $creditPackage->id,
        'currency' => 'USD',
    ]);

    assertDatabaseHas('billing_products', [
        'id' => $subscriptionPlan->id,
        'currency' => 'USD',
    ]);
});

it('reorders top ups and subscriptions for public billing display', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    BillingProduct::query()->update([
        'is_active' => false,
    ]);

    $creditA = BillingProduct::query()->create([
        'type' => 'credits',
        'slug' => 'credit-a',
        'name' => 'Credit A',
        'name_en' => 'Credit A',
        'name_ar' => 'شحن أ',
        'currency' => 'EGP',
        'price_cents' => 1000,
        'price' => 10,
        'credits' => 100,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $creditB = BillingProduct::query()->create([
        'type' => 'credits',
        'slug' => 'credit-b',
        'name' => 'Credit B',
        'name_en' => 'Credit B',
        'name_ar' => 'شحن ب',
        'currency' => 'EGP',
        'price_cents' => 2000,
        'price' => 20,
        'credits' => 200,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $creditC = BillingProduct::query()->create([
        'type' => 'credits',
        'slug' => 'credit-c',
        'name' => 'Credit C',
        'name_en' => 'Credit C',
        'name_ar' => 'شحن ج',
        'currency' => 'EGP',
        'price_cents' => 3000,
        'price' => 30,
        'credits' => 300,
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $subscriptionA = BillingProduct::query()->create([
        'type' => 'subscription',
        'slug' => 'subscription-a',
        'name' => 'Subscription A',
        'name_en' => 'Subscription A',
        'name_ar' => 'اشتراك أ',
        'currency' => 'EGP',
        'price_cents' => 5000,
        'price' => 50,
        'credits' => 500,
        'renews_in_days' => 30,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $subscriptionB = BillingProduct::query()->create([
        'type' => 'subscription',
        'slug' => 'subscription-b',
        'name' => 'Subscription B',
        'name_en' => 'Subscription B',
        'name_ar' => 'اشتراك ب',
        'currency' => 'EGP',
        'price_cents' => 7000,
        'price' => 70,
        'credits' => 700,
        'renews_in_days' => 30,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    actingAs($user);

    putJson(route('billing.manage.credit-packages.reorder'), [
        'product_ids' => [$creditC->id, $creditA->id, $creditB->id],
    ])->assertNoContent();

    putJson(route('billing.manage.subscription-plans.reorder'), [
        'product_ids' => [$subscriptionB->id, $subscriptionA->id],
    ])->assertNoContent();

    assertDatabaseHas('billing_products', [
        'id' => $creditC->id,
        'sort_order' => 1,
    ]);

    assertDatabaseHas('billing_products', [
        'id' => $creditA->id,
        'sort_order' => 2,
    ]);

    assertDatabaseHas('billing_products', [
        'id' => $creditB->id,
        'sort_order' => 3,
    ]);

    assertDatabaseHas('billing_products', [
        'id' => $subscriptionB->id,
        'sort_order' => 1,
    ]);

    assertDatabaseHas('billing_products', [
        'id' => $subscriptionA->id,
        'sort_order' => 2,
    ]);

    $payload = app(BillingCatalogService::class)->publicPayload();

    expect(array_column($payload['creditPackages'], 'slug'))->toBe([
        'credit-c',
        'credit-a',
        'credit-b',
    ]);

    expect(array_column($payload['subscriptionPlans'], 'slug'))->toBe([
        'subscription-b',
        'subscription-a',
    ]);
});
