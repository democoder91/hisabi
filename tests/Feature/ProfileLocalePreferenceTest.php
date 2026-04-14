<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('updates the authenticated user locale preference', function (string $locale): void {
    /** @var User $user */
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    actingAs($user);

    putJson('/api/v1/user/profile', [
        'locale' => $locale,
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email);

    expect($user->fresh()->locale)->toBe($locale);
})->with([
    'english' => 'en',
    'arabic' => 'ar',
]);

it('rejects unsupported locale preferences', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'locale' => 'en',
    ]);
    $originalLocale = $user->locale;

    actingAs($user);

    putJson('/api/v1/user/profile', [
        'locale' => 'fr',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);

    expect($user->fresh()->locale)->toBe($originalLocale);
});
