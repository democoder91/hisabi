<?php

declare(strict_types=1);

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('filters accounts by currency and access level', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var User $sharedOwner */
    $sharedOwner = User::factory()->create();

    $ownedUsdAccount = Account::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'name' => ['en' => 'Owned USD'],
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'currency' => 'EUR',
        'name' => ['en' => 'Owned EUR'],
    ]);

    $sharedUsdAccount = Account::factory()->create([
        'user_id' => $sharedOwner->id,
        'currency' => 'USD',
        'name' => ['en' => 'Shared USD'],
    ]);
    $sharedUsdAccount->sharedUsers()->attach($user->id, ['permission_level' => Account::PERMISSION_VIEW]);

    actingAs($user);

    $ownedResponse = getJson('/api/v1/accounts?filter[currency]=USD&filter[access]=owned');
    $ownedResponse->assertOk()
        ->assertJsonPath('paginatorInfo.total', 1)
        ->assertJsonPath('data.0.id', $ownedUsdAccount->id)
        ->assertJsonPath('data.0.currency', 'USD')
        ->assertJsonPath('data.0.permissionLevel', 'owner');

    $sharedResponse = getJson('/api/v1/accounts?filter[currency]=USD&filter[access]=shared');
    $sharedResponse->assertOk()
        ->assertJsonPath('paginatorInfo.total', 1)
        ->assertJsonPath('data.0.id', $sharedUsdAccount->id)
        ->assertJsonPath('data.0.currency', 'USD')
        ->assertJsonPath('data.0.permissionLevel', Account::PERMISSION_VIEW);
});

it('filters accounts by type', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $assetAccount = Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_ASSET,
        'name' => ['en' => 'Savings'],
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_EXPENSE,
        'name' => ['en' => 'Groceries'],
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_INCOME,
        'name' => ['en' => 'Salary'],
    ]);

    actingAs($user);

    $response = getJson('/api/v1/accounts?filter[type]=asset');
    $response->assertOk()
        ->assertJsonPath('paginatorInfo.total', 1)
        ->assertJsonPath('data.0.id', $assetAccount->id)
        ->assertJsonPath('data.0.type', Account::TYPE_ASSET);

    $noResultsResponse = getJson('/api/v1/accounts?filter[type]=liability');
    $noResultsResponse->assertOk()
        ->assertJsonPath('paginatorInfo.total', 0);
});