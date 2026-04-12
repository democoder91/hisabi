<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders the public landing page for guests', function () {
    $response = get('/');

    $response->assertSuccessful();
    $response->assertSee('&quot;component&quot;:&quot;Landing&quot;', false);
    $response->assertSee('Nexo');
    $response->assertDontSee('Hisabi');
    $response->assertDontSee('rel="icon"', false);
    $response->assertDontSee('apple-touch-icon', false);
});

it('redirects authenticated users from the landing page to the dashboard', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    actingAs($user);
    $response = get('/');

    $response->assertRedirect('/dashboard');
});
