<?php

use App\Models\BillingGrantAudit;
use App\Models\BillingProduct;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('allows super users to open the billing user access page', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create();

    actingAs($superUser);

    get(route('billing.manage.users'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Billing/Users')
            ->has('users', 2)
            ->where('users.0.email', $targetUser->email)
            ->has('grantOptions.creditPackages')
            ->has('grantOptions.subscriptionPlans')
            ->where('pagination.total', 2));
});

it('forbids non super users from opening the billing user access page', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => false,
    ]);

    actingAs($user);

    get(route('billing.manage.users'))->assertForbidden();
});

it('allows a super user to grant a credit package and records an audit', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create([
        'available_credits' => 5,
    ]);

    $creditPackage = BillingProduct::query()->where('slug', 'starter-100')->firstOrFail();

    actingAs($superUser);

    post(route('billing.manage.users.grants.store', $targetUser), [
        'billing_product_id' => $creditPackage->id,
    ])->assertRedirect();

    expect($targetUser->fresh()->available_credits)->toBe(5 + (int) $creditPackage->credits);

    assertDatabaseHas('billing_grant_audits', [
        'admin_user_id' => $superUser->id,
        'target_user_id' => $targetUser->id,
        'billing_product_id' => $creditPackage->id,
        'grant_type' => 'credits',
    ]);

    $audit = BillingGrantAudit::query()->latest()->firstOrFail();

    expect($audit->product_snapshot['slug'])->toBe('starter-100');
    expect($audit->old_values['available_credits'])->toBe(5);
    expect($audit->new_values['available_credits'])->toBe(5 + (int) $creditPackage->credits);
});

it('extends an existing subscription when a super user grants a subscription plan', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create([
        'available_credits' => 10,
        'trial_ends_at' => now()->addDays(3),
    ]);

    $initialRenewal = now()->addDays(7)->startOfSecond();

    Subscription::query()->create([
        'user_id' => $targetUser->id,
        'plan_name' => 'Existing Plan',
        'status' => 'active',
        'paymob_order_id' => null,
        'renews_at' => $initialRenewal,
    ]);

    $subscriptionPlan = BillingProduct::query()->where('slug', 'pro-monthly')->firstOrFail();

    actingAs($superUser);

    post(route('billing.manage.users.grants.store', $targetUser), [
        'billing_product_id' => $subscriptionPlan->id,
    ])->assertRedirect();

    $updatedUser = $targetUser->fresh();
    $updatedSubscription = Subscription::query()->where('user_id', $targetUser->id)->firstOrFail();
    $expectedRenewal = $initialRenewal->copy()->addDays((int) $subscriptionPlan->renews_in_days);

    expect($updatedUser->available_credits)->toBe(10 + (int) $subscriptionPlan->credits);
    expect($updatedUser->trial_ends_at)->toBeNull();
    expect($updatedSubscription->plan_name)->toBe($subscriptionPlan->localizedName($targetUser->locale));
    expect($updatedSubscription->renews_at->toDateTimeString())->toBe($expectedRenewal->toDateTimeString());

    $audit = BillingGrantAudit::query()->latest()->firstOrFail();

    expect($audit->grant_type)->toBe('subscription');
    expect($audit->old_values['subscription']['plan_name'])->toBe('Existing Plan');
    expect($audit->new_values['subscription']['plan_name'])->toBe($subscriptionPlan->localizedName($targetUser->locale));
    expect($audit->new_values['available_credits'])->toBe(10 + (int) $subscriptionPlan->credits);
});
