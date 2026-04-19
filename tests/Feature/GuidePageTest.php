<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows the guide page to authenticated users', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    get(route('guide'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Guide/Index'));
});

it('redirects guests away from the guide page', function () {
    get(route('guide'))->assertRedirect(route('login'));
});