<?php

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns converted expense totals in the users effective currency', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'default_currency' => 'EUR',
    ]);

    ExchangeRate::query()->updateOrCreate(
        ['user_id' => $user->id, 'currency' => 'USD'],
        ['rate' => 1, 'source' => 'manual', 'last_synced_at' => now()],
    );

    ExchangeRate::query()->updateOrCreate(
        ['user_id' => $user->id, 'currency' => 'EUR'],
        ['rate' => 0.8, 'source' => 'manual', 'last_synced_at' => now()],
    );

    $checking = Account::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'type' => Account::TYPE_ASSET,
    ]);

    $food = Account::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'type' => Account::TYPE_EXPENSE,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'category_id' => null,
        'from_account_id' => $checking->id,
        'to_account_id' => $food->id,
        'amount' => 100,
        'transaction_type' => Transaction::TYPE_DEBIT,
        'note' => 'Groceries',
        'currency' => 'USD',
        'created_at' => now(),
    ]);

    $from = now()->subDay()->toDateString();
    $to = now()->addDay()->toDateString();

    actingAs($user);

    $response = getJson("/api/v1/metrics/total-expenses?from={$from}&to={$to}");

    $response->assertOk()
        ->assertJsonPath('data.value', 80)
        ->assertJsonPath('data.previous', 0)
        ->assertJsonPath('data.currency', 'EUR');
});