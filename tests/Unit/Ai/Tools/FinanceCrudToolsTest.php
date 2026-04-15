<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateAccountTool;
use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\CreateCategoryTool;
use App\Ai\Tools\CreateTransferTool;
use App\Ai\Tools\EditAccountTool;
use App\Ai\Tools\EditBudgetTool;
use App\Ai\Tools\EditCategoryTool;
use App\Ai\Tools\EditTransactionTool;
use App\Ai\Tools\ListAccountsTool;
use App\Ai\Tools\ListBudgetsTool;
use App\Ai\Tools\ListCategoriesTool;
use App\Ai\Tools\ListTransactionsTool;
use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Tests\TestCase;

class FinanceCrudToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_account_tools_can_create_list_and_edit_accounts(): void
    {
        $createOutput = (new CreateAccountTool())->handle(new Request([
            'name_en' => 'Primary Wallet',
            'balance' => 500,
        ]));

        $account = Account::query()->where('user_id', $this->user->id)->latest('id')->first();
        $listOutput = (new ListAccountsTool())->handle(new Request([
            'search' => 'Wallet',
        ]));
        $editOutput = (new EditAccountTool())->handle(new Request([
            'account_id' => $account->id,
            'name_en' => 'Emergency Fund',
            'balance' => 900.50,
        ]));

        $account->refresh();

        $this->assertStringContainsString('Primary Wallet', $createOutput);
        $this->assertStringContainsString((string) $account->id, $listOutput);
        $this->assertStringContainsString('Emergency Fund', $editOutput);
        $this->assertSame('Emergency Fund', $account->getTranslation('name', 'en'));
        $this->assertSame(900.50, $account->balance);
    }

    public function test_category_tools_can_create_list_and_edit_categories(): void
    {
        $createOutput = (new CreateCategoryTool())->handle(new Request([
            'name_en' => 'Dining',
            'type' => 'expenses',
            'color' => 'red',
            'icon' => 'utensils',
        ]));

        $category = Category::query()->latest('id')->first();
        $listOutput = (new ListCategoriesTool())->handle(new Request([
            'type' => Category::EXPENSES,
        ]));
        $editOutput = (new EditCategoryTool())->handle(new Request([
            'category_id' => $category->id,
            'name_en' => 'Restaurants',
            'color' => 'orange',
        ]));

        $category->refresh();

        $this->assertStringContainsString('Dining', $createOutput);
        $this->assertStringContainsString((string) $category->id, $listOutput);
        $this->assertStringContainsString('Restaurants', $editOutput);
        $this->assertSame('Restaurants', $category->getTranslation('name', 'en'));
        $this->assertSame('orange', $category->color);
    }

    public function test_budget_tools_can_create_list_and_edit_budgets(): void
    {
        $food = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food', 'ar' => null],
        ]);
        $transport = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Transport', 'ar' => null],
        ]);

        $createOutput = (new CreateBudgetTool())->handle(new Request([
            'name_en' => 'Monthly Essentials',
            'amount' => 1200,
            'start_at' => '2026-04-01',
            'period' => 1,
            'reoccurrence' => 'monthly',
            'category_ids' => [$food->id, $transport->id],
        ]));

        $budget = Budget::query()->with('categories')->latest('id')->first();
        $listOutput = (new ListBudgetsTool())->handle(new Request([
            'reoccurrence' => Budget::MONTHLY,
        ]));
        $editOutput = (new EditBudgetTool())->handle(new Request([
            'budget_id' => $budget->id,
            'amount' => 1500,
            'category_ids' => [$food->id],
            'saving' => true,
        ]));

        $budget->refresh()->load('categories');

        $this->assertStringContainsString('Monthly Essentials', $createOutput);
        $this->assertStringContainsString((string) $budget->id, $listOutput);
        $this->assertStringContainsString('1500.00', $editOutput);
        $this->assertSame(1500.0, $budget->amount);
        $this->assertTrue($budget->saving);
        $this->assertSame([$food->id], $budget->categories->pluck('id')->all());
    }

    public function test_transaction_tools_can_list_and_edit_transactions(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Checking', 'ar' => null],
        ]);
        $food = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food', 'ar' => null],
            'type' => Category::EXPENSES,
        ]);
        $savings = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => null],
            'type' => Category::SAVINGS,
        ]);
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'category_id' => $food->id,
            'amount' => 25,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Lunch',
            'created_at' => '2026-04-12',
        ]);

        $listOutput = (new ListTransactionsTool())->handle(new Request([
            'account_id' => $account->id,
        ]));
        $editOutput = (new EditTransactionTool())->handle(new Request([
            'transaction_id' => $transaction->id,
            'amount' => 40,
            'category_id' => $savings->id,
            'note' => 'Savings transfer',
        ]));

        $transaction = Transaction::withoutGlobalScopes()->with(['account', 'category'])->find($transaction->id);

        $this->assertStringContainsString('Lunch', $listOutput);
        $this->assertStringContainsString('Savings transfer', $editOutput);
        $this->assertSame(40.0, $transaction->amount);
        $this->assertSame($savings->id, $transaction->category_id);
        $this->assertSame(Transaction::TYPE_DEBIT, $transaction->transaction_type);
        $this->assertSame('Savings transfer', $transaction->note);
    }

    public function test_transfer_tool_can_move_money_between_editable_accounts(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Checking', 'ar' => null],
            'balance' => 300,
            'currency' => 'AED',
        ]);
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => null],
            'balance' => 80,
            'currency' => 'AED',
        ]);

        $output = (new CreateTransferTool())->handle(new Request([
            'amount' => 45,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
            'note' => 'Monthly top-up',
        ]));

        $transactions = Transaction::withoutGlobalScopes()->get();

        $this->assertCount(2, $transactions);
        $this->assertStringContainsString('Transfer created successfully', $output);
        $this->assertSame(255.0, $sourceAccount->fresh()->balance);
        $this->assertSame(125.0, $destinationAccount->fresh()->balance);
    }

    public function test_list_transactions_tool_excludes_soft_deleted_transactions(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 10,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Visible lunch',
        ]);

        $deletedTransaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 20,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Deleted lunch',
        ]);
        $deletedTransaction->delete();

        $output = (new ListTransactionsTool())->handle(new Request([
            'account_id' => $account->id,
        ]));

        $this->assertStringContainsString('Visible lunch', $output);
        $this->assertStringNotContainsString('Deleted lunch', $output);
    }

    public function test_edit_transaction_tool_rejects_soft_deleted_transactions(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
        ]);

        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 20,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Deleted draft',
        ]);
        $transaction->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The specified transaction was not found or is not accessible.');

        (new EditTransactionTool())->handle(new Request([
            'transaction_id' => $transaction->id,
            'note' => 'Updated',
        ]));
    }

    public function test_edit_transaction_tool_rejects_reusing_a_participant_owned_category_on_a_shared_account(): void
    {
        $owner = User::factory()->create();
        $sharedAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Shared Wallet', 'ar' => null],
        ]);
        $sharedAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $ownersCategory = Category::factory()->create([
            'user_id' => $owner->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Owner Food', 'ar' => null],
        ]);

        $editorsCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Editor Food', 'ar' => null],
        ]);

        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $sharedAccount->id,
            'category_id' => $ownersCategory->id,
            'amount' => 20,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Shared lunch',
            'created_at' => '2026-04-12',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The selected category is invalid for the chosen account.');

        (new EditTransactionTool())->handle(new Request([
            'transaction_id' => $transaction->id,
            'category_id' => $editorsCategory->id,
            'note' => 'Editor category attempt',
        ]));
    }
}
