<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\json;

uses(RefreshDatabase::class);

it('does not expose the legacy categories web page', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    get('/categories')
        ->assertNotFound();
});

dataset('legacy-category-api-endpoints', [
    'index' => ['GET', '/api/v1/categories/all', []],
    'store' => ['POST', '/api/v1/categories', [
        'name_en' => 'Dining',
        'type' => 'expenses',
        'color' => 'red',
        'icon' => 'utensils',
    ]],
    'show' => ['GET', '/api/v1/categories/1', []],
    'update' => ['PUT', '/api/v1/categories/1', [
        'name_en' => 'Dining',
        'type' => 'expenses',
        'color' => 'red',
        'icon' => 'utensils',
    ]],
    'destroy' => ['DELETE', '/api/v1/categories/1', []],
]);

it('does not expose legacy category api endpoints', function (string $method, string $uri, array $payload) {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user, 'sanctum');

    $response = json($method, $uri, $payload);

    $response->assertNotFound();
})->with('legacy-category-api-endpoints');