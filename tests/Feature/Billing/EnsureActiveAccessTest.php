<?php

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects users with expired trial and no active subscription to billing', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);

    actingAs($user);

    $response = get('/accounts');

    $response->assertRedirect(route('billing.index'));
});

it('allows users with an active trial to access protected pages', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => Carbon::now()->addDay(),
    ]);

    actingAs($user);

    $response = get('/accounts');

    $response->assertOk();
});

it('allows users with an active subscription to access protected pages', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);

    Subscription::query()->create([
        'user_id' => $user->id,
        'plan_name' => 'Pro Monthly',
        'status' => 'active',
        'paymob_order_id' => 1001,
        'renews_at' => Carbon::now()->addMonth(),
    ]);

    actingAs($user);

    $response = get('/accounts');

    $response->assertOk();
});

it('treats super users as having active subscription access everywhere', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
        'is_super' => true,
    ]);

    expect($user->hasActiveSubscription())->toBeTrue();
    expect($user->hasActiveAccess())->toBeTrue();

    actingAs($user);

    $response = get('/accounts');

    $response->assertOk();
});

it('keeps the billing page accessible when access is expired', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);

    actingAs($user);

    $response = get('/billing');

    $response->assertOk();
});
