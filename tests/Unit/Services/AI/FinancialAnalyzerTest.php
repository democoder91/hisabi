<?php

namespace Tests\Unit\Services\AI;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category as DomainCategory;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use App\Services\AI\FinancialAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_uncategorized_credit_transactions_in_income_summary(): void
    {
        $user = User::factory()->create(['default_currency' => 'EGP']);

        Transaction::factory()->create([
            'amount' => 500,
            'brand_id' => null,
            'transaction_type' => Transaction::TYPE_CREDIT,
            'created_at' => now()->subDays(2),
        ]);

        $summary = (new FinancialAnalyzer())->generateSummary($user);

        $this->assertStringContainsString('Total Income: EGP 500.00', $summary);
        $this->assertStringContainsString('Uncategorized: EGP 500.00', $summary);
        $this->assertStringNotContainsString('No income data available.', $summary);
    }

    public function test_it_includes_uncategorized_transactions_in_expense_summary(): void
    {
        $user = User::factory()->create(['default_currency' => 'EGP']);

        Transaction::factory()->create([
            'amount' => 950,
            'brand_id' => null,
            'created_at' => now()->subDays(2),
        ]);

        $summary = (new FinancialAnalyzer())->generateSummary($user);

        $this->assertStringContainsString('Total Expenses: EGP 950.00', $summary);
        $this->assertStringContainsString('Uncategorized: EGP 950.00', $summary);
        $this->assertStringNotContainsString('No expense data available.', $summary);
        $this->assertStringNotContainsString('No brand data available.', $summary);
    }

    public function test_it_combines_categorized_and_uncategorized_expenses(): void
    {
        $user = User::factory()->create(['default_currency' => 'EGP']);
        $category = Category::factory()->create(['type' => Category::EXPENSES, 'name' => 'Food']);
        $brand = Brand::factory()->create(['category_id' => $category->id, 'name' => 'Cafe']);

        Transaction::factory()->create([
            'amount' => 600,
            'brand_id' => $brand->id,
            'created_at' => now()->subDay(),
        ]);

        Transaction::factory()->create([
            'amount' => 350,
            'brand_id' => null,
            'created_at' => now()->subDay(),
        ]);

        $summary = (new FinancialAnalyzer())->generateSummary($user);

        $this->assertStringContainsString('Total Expenses: EGP 950.00', $summary);
        $this->assertStringContainsString('Food: EGP 600.00', $summary);
        $this->assertStringContainsString('Uncategorized: EGP 350.00', $summary);
    }

    public function test_it_groups_category_totals_for_the_requested_user_without_only_full_group_by_errors(): void
    {
        $user = User::factory()->create(['default_currency' => 'EGP']);
        $otherUser = User::factory()->create(['default_currency' => 'USD']);

        $account = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Wallet'],
            'balance' => 0,
            'currency' => 'EGP',
        ]);

        $otherAccount = Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => ['en' => 'Other Wallet'],
            'balance' => 0,
            'currency' => 'USD',
        ]);

        $food = DomainCategory::factory()->create([
            'user_id' => $user->id,
            'type' => DomainCategory::EXPENSES,
            'name' => ['en' => 'Food', 'ar' => null],
        ]);

        $travel = DomainCategory::factory()->create([
            'user_id' => $user->id,
            'type' => DomainCategory::EXPENSES,
            'name' => ['en' => 'Travel', 'ar' => null],
        ]);

        $otherCategory = DomainCategory::factory()->create([
            'user_id' => $otherUser->id,
            'type' => DomainCategory::EXPENSES,
            'name' => ['en' => 'Other User Expense', 'ar' => null],
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $food->id,
            'amount' => 100,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subDay(),
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $food->id,
            'amount' => 50,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subHours(12),
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $travel->id,
            'amount' => 80,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'EGP',
            'created_at' => now()->subDay(),
        ]);

        Transaction::factory()->create([
            'account_id' => $otherAccount->id,
            'category_id' => $otherCategory->id,
            'amount' => 999,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'USD',
            'created_at' => now()->subDay(),
        ]);

        $summary = (new FinancialAnalyzer())->generateSummary($user);

        $this->assertStringContainsString('Food: EGP 150.00', $summary);
        $this->assertStringContainsString('Travel: EGP 80.00', $summary);
        $this->assertStringNotContainsString('Other User Expense', $summary);
        $this->assertStringNotContainsString('999.00', $summary);
    }
}