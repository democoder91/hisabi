<?php

declare(strict_types=1);

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes misencoded arabic account names in account listings', function () {
    $user = User::factory()->create([
        'locale' => 'ar',
    ]);

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Checking',
            'ar' => 'Ø¨Ù†Ùƒ Ø§Ù„Ù‚Ø§Ù‡Ø±Ø© Ø¬Ø§Ø±ÙŠ',
        ],
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/accounts/all');

    $response->assertOk();

    $payload = collect($response->json('data'))->firstWhere('id', $account->id);

    expect($payload)->not->toBeNull();
    expect($payload['name'])->toBe('بنك القاهرة جاري');
    expect($payload['name_translations']['ar'])->toBe('بنك القاهرة جاري');
    expect($payload['name_translations']['en'])->toBe('Checking');
});

it('normalizes misencoded arabic account names when creating accounts', function () {
    $user = User::factory()->create([
        'locale' => 'ar',
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/accounts', [
        'name' => [
            'en' => 'Checking',
            'ar' => 'Ø¨Ù†Ùƒ Ø§Ù„Ù‚Ø§Ù‡Ø±Ø© Ø¬Ø§Ø±ÙŠ',
        ],
        'balance' => 1500,
        'currency' => 'usd',
        'type' => Account::TYPE_ASSET,
    ]);

    $response->assertCreated()
        ->assertJsonPath('account.name', 'بنك القاهرة جاري')
        ->assertJsonPath('account.name_translations.ar', 'بنك القاهرة جاري')
        ->assertJsonPath('account.name_translations.en', 'Checking');

    $account = Account::query()->latest('id')->firstOrFail();

    expect($account->getTranslation('name', 'ar'))->toBe('بنك القاهرة جاري');
    expect($account->getTranslation('name', 'en'))->toBe('Checking');
});