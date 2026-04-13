<?php

use App\Models\BillingProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
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

it('updates bilingual billing products with whole-unit prices and explicit subscription credits', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => true,
    ]);

    actingAs($user);

    $response = put(route('billing.manage.update'), [
        'currency' => 'EGP',
        'credit_packages' => [
            [
                'slug' => 'starter-100',
                'name_en' => 'Starter Top Up',
                'name_ar' => 'شحن البداية',
                'price' => 10,
                'credits' => 100,
            ],
            [
                'slug' => 'growth-250',
                'name_en' => 'Growth Top Up',
                'name_ar' => 'شحن النمو',
                'price' => 25,
                'credits' => 250,
            ],
            [
                'slug' => 'scale-500',
                'name_en' => 'Scale Top Up',
                'name_ar' => 'شحن التوسع',
                'price' => 50,
                'credits' => 500,
            ],
        ],
        'subscription_plans' => [
            [
                'slug' => 'starter-monthly',
                'name_en' => 'Starter Subscription',
                'name_ar' => 'اشتراك البداية',
                'price' => 20,
                'credits' => 300,
                'renews_in_days' => 30,
            ],
            [
                'slug' => 'pro-monthly',
                'name_en' => 'Pro Subscription',
                'name_ar' => 'اشتراك برو',
                'price' => 40,
                'credits' => 600,
                'renews_in_days' => 30,
            ],
        ],
    ]);

    $response->assertRedirect(route('billing.manage'));

    assertDatabaseHas('billing_products', [
        'slug' => 'starter-100',
        'type' => 'credits',
        'name' => 'Starter Top Up',
        'name_en' => 'Starter Top Up',
        'name_ar' => 'شحن البداية',
        'currency' => 'EGP',
        'price' => 10,
        'price_cents' => 1000,
        'credits' => 100,
    ]);

    assertDatabaseHas('billing_products', [
        'slug' => 'pro-monthly',
        'type' => 'subscription',
        'name' => 'Pro Subscription',
        'name_en' => 'Pro Subscription',
        'name_ar' => 'اشتراك برو',
        'currency' => 'EGP',
        'price' => 40,
        'price_cents' => 4000,
        'credits' => 600,
        'renews_in_days' => 30,
    ]);
});
