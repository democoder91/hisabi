<?php

declare(strict_types=1);

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use App\Services\AI\FinancialAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Account::forgetCachedTypeColumnSupport();
});

afterEach(function () {
    Account::forgetCachedTypeColumnSupport();
});

it('creates accounts when the legacy accounts table does not have a type column', function () {
    $user = User::factory()->create();
    Schema::shouldReceive('hasColumn')
        ->with('accounts', 'type')
        ->andReturn(false);
    Schema::shouldReceive('hasColumn')
        ->with('accounts', 'parent_id')
        ->andReturn(false);

    $response = $this->actingAs($user)->postJson('/api/v1/accounts', [
        'name' => [
            'en' => 'Electricity',
        ],
        'balance' => 0,
        'currency' => 'usd',
        'type' => Account::TYPE_EXPENSE,
    ]);

    $response->assertCreated()
        ->assertJsonPath('account.name', 'Electricity')
        ->assertJsonPath('account.type', Account::TYPE_ASSET);

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'currency' => 'USD',
    ]);
});

it('returns a legacy financial summary instead of failing when account types are unavailable', function () {
    $user = User::factory()->create(['default_currency' => 'EGP']);

    $wallet = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Wallet'],
        'balance' => 0,
        'type' => Account::TYPE_ASSET,
        'currency' => 'EGP',
    ]);

    $groceries = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Groceries'],
        'balance' => 0,
        'type' => Account::TYPE_EXPENSE,
        'currency' => 'EGP',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'category_id' => null,
        'from_account_id' => $wallet->id,
        'to_account_id' => $groceries->id,
        'amount' => 125,
        'transaction_type' => Transaction::TYPE_DEBIT,
        'currency' => 'EGP',
        'created_at' => now()->subDay(),
    ]);

    Account::forgetCachedTypeColumnSupport();

    Schema::shouldReceive('hasColumn')
        ->with('accounts', 'type')
        ->andReturn(false);
    Schema::shouldReceive('hasColumn')
        ->with('accounts', 'parent_id')
        ->andReturn(false);

    $summary = app(FinancialAnalyzer::class)->generateSummary($user);

    expect($summary)->toContain('Accessible Accounts: 2')
        ->toContain('Recent Transactions: 1')
        ->toContain('Wallet')
        ->toContain('Groceries')
        ->toContain('Legacy Schema Notice');
});