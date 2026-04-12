<?php

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns converted expense totals in the users effective currency', function () {
    $user = User::factory()->create([
        'default_currency' => 'EUR',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'type' => Category::EXPENSES,
    ]);

    ExchangeRate::query()->updateOrCreate(
        ['user_id' => $user->id, 'currency' => 'USD'],
        ['rate' => 1, 'source' => 'manual', 'last_synced_at' => now()],
    );

    ExchangeRate::query()->updateOrCreate(
        ['user_id' => $user->id, 'currency' => 'EUR'],
        ['rate' => 0.8, 'source' => 'manual', 'last_synced_at' => now()],
    );

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
    ]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 100,
        'created_at' => now(),
    ]);

    $from = now()->subDay()->toDateString();
    $to = now()->addDay()->toDateString();

    $response = $this->actingAs($user)->getJson("/api/v1/metrics/total-expenses?from={$from}&to={$to}");

    $response->assertOk()
        ->assertJsonPath('data.value', 80)
        ->assertJsonPath('data.previous', 0)
        ->assertJsonPath('data.currency', 'EUR');
});