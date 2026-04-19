<?php

namespace Tests\Unit\Services\AI;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use App\Services\AI\FinancialAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_account_totals_for_the_requested_user_without_cross_user_leakage(): void
    {
        $user = User::factory()->create(['default_currency' => 'EGP']);
        $otherUser = User::factory()->create(['default_currency' => 'USD']);

        $wallet = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Wallet'],
            'balance' => 0,
            'type' => Account::TYPE_ASSET,
            'currency' => 'EGP',
        ]);

        $salary = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Salary'],
            'balance' => 0,
            'type' => Account::TYPE_INCOME,
            'currency' => 'EGP',
        ]);

        $groceries = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Groceries'],
            'balance' => 0,
            'type' => Account::TYPE_EXPENSE,
            'currency' => 'EGP',
        ]);

        $travel = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Travel'],
            'balance' => 0,
            'type' => Account::TYPE_EXPENSE,
            'currency' => 'EGP',
        ]);

        $otherAccount = Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => ['en' => 'Other Wallet'],
            'balance' => 0,
            'type' => Account::TYPE_ASSET,
            'currency' => 'USD',
        ]);

        $otherIncome = Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => ['en' => 'Other Income'],
            'balance' => 0,
            'type' => Account::TYPE_INCOME,
            'currency' => 'USD',
        ]);

        Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $salary->id,
            'category_id' => null,
            'from_account_id' => $salary->id,
            'to_account_id' => $wallet->id,
            'amount' => 1000,
            'transaction_type' => Transaction::TYPE_CREDIT,
            'currency' => 'EGP',
            'created_at' => now()->subDay(),
        ]);

        Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $wallet->id,
            'category_id' => null,
            'from_account_id' => $wallet->id,
            'to_account_id' => $groceries->id,
            'amount' => 100,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subDay(),
        ]);

        Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $wallet->id,
            'category_id' => null,
            'from_account_id' => $wallet->id,
            'to_account_id' => $groceries->id,
            'amount' => 50,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subHours(12),
        ]);

        Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $wallet->id,
            'category_id' => null,
            'from_account_id' => $wallet->id,
            'to_account_id' => $travel->id,
            'amount' => 80,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subDay(),
        ]);

        Transaction::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherIncome->id,
            'category_id' => null,
            'from_account_id' => $otherIncome->id,
            'to_account_id' => $otherAccount->id,
            'amount' => 999,
            'transaction_type' => Transaction::TYPE_CREDIT,
            'currency' => 'USD',
            'created_at' => now()->subDay(),
        ]);

        $summary = (new FinancialAnalyzer())->generateSummary($user);

        $this->assertStringContainsString('Groceries: EGP 150.00', $summary);
        $this->assertStringContainsString('Travel: EGP 80.00', $summary);
        $this->assertStringContainsString('Salary: EGP 1,000.00', $summary);
        $this->assertStringNotContainsString('Other Income', $summary);
        $this->assertStringNotContainsString('999.00', $summary);
    }
}
