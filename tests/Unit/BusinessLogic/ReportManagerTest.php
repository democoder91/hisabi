<?php

use App\BusinessLogic\ReportManager;
use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates account based sections for the requested user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $salary = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Salary'],
        'type' => Account::TYPE_INCOME,
        'currency' => 'AED',
    ]);
    $wallet = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Wallet'],
        'type' => Account::TYPE_ASSET,
        'currency' => 'AED',
    ]);
    $groceries = Account::factory()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Groceries'],
        'type' => Account::TYPE_EXPENSE,
        'currency' => 'AED',
        'created_at' => '2026-04-10 10:00:00',
    ]);

    $otherSalary = Account::factory()->create([
        'user_id' => $otherUser->id,
        'name' => ['en' => 'Other Salary'],
        'type' => Account::TYPE_INCOME,
        'currency' => 'AED',
    ]);
    $otherWallet = Account::factory()->create([
        'user_id' => $otherUser->id,
        'name' => ['en' => 'Other Wallet'],
        'type' => Account::TYPE_ASSET,
        'currency' => 'AED',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'from_account_id' => $salary->id,
        'to_account_id' => $wallet->id,
        'amount' => 1000,
        'transaction_type' => Transaction::TYPE_CREDIT,
        'currency' => 'AED',
        'created_at' => '2026-04-05 09:00:00',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'from_account_id' => $wallet->id,
        'to_account_id' => $groceries->id,
        'amount' => 300,
        'transaction_type' => Transaction::TYPE_DEBIT,
        'currency' => 'AED',
        'created_at' => '2026-04-08 10:00:00',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'from_account_id' => $salary->id,
        'to_account_id' => $wallet->id,
        'amount' => 500,
        'transaction_type' => Transaction::TYPE_CREDIT,
        'currency' => 'AED',
        'created_at' => '2026-03-05 09:00:00',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'account_id' => $wallet->id,
        'from_account_id' => $wallet->id,
        'to_account_id' => $groceries->id,
        'amount' => 100,
        'transaction_type' => Transaction::TYPE_DEBIT,
        'currency' => 'AED',
        'created_at' => '2026-03-08 10:00:00',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'user_id' => $otherUser->id,
        'account_id' => $otherWallet->id,
        'from_account_id' => $otherSalary->id,
        'to_account_id' => $otherWallet->id,
        'amount' => 9999,
        'transaction_type' => Transaction::TYPE_CREDIT,
        'currency' => 'AED',
        'created_at' => '2026-04-09 08:00:00',
    ]);

    $report = app(ReportManager::class)->generate('2026-04-01', '2026-04-30', $user);

    expect($report)->toHaveKeys(['Overview', 'Income Accounts', 'Expense Accounts', 'Asset Accounts']);

    $overview = collect($report['Overview'])->keyBy('name');
    expect($overview['Total Income']['total_current_month'])->toBe(1000.0);
    expect($overview['Total Income']['total_previous_month'])->toBe(500.0);
    expect($overview['Total Expenses']['total_current_month'])->toBe(300.0);
    expect($overview['Total Expenses']['total_previous_month'])->toBe(100.0);
    expect($overview['Total Assets']['total_current_month'])->toBe(700.0);
    expect($overview['Total Assets']['total_previous_month'])->toBe(400.0);

    $incomeRows = collect($report['Income Accounts']);
    expect($incomeRows->pluck('name')->all())->toContain('All', 'Salary');
    expect($incomeRows->pluck('name')->all())->not->toContain('Other Salary');

    $expenseRows = collect($report['Expense Accounts']);
    $groceriesRow = $expenseRows->firstWhere('name', 'Groceries');
    expect($groceriesRow['total_current_month'])->toBe(300.0);
    expect($groceriesRow['is_new'])->toBeTrue();
});