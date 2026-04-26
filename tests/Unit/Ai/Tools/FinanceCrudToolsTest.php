<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateAccountTool;
use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\CreateTransferTool;
use App\Ai\Tools\EditAccountTool;
use App\Ai\Tools\EditBudgetTool;
use App\Ai\Tools\EditTransactionTool;
use App\Ai\Tools\ListAccountsTool;
use App\Ai\Tools\ListBudgetsTool;
use App\Ai\Tools\ListTransactionsTool;
use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        Account::forgetCachedTypeColumnSupport();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Account::forgetCachedTypeColumnSupport();

        parent::tearDown();
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

    public function test_create_account_tool_skips_parent_id_when_the_legacy_schema_lacks_that_column(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('accounts', 'type')
            ->andReturn(true);
        Schema::shouldReceive('hasColumn')
            ->with('accounts', 'parent_id')
            ->andReturn(false);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $createOutput = (new CreateAccountTool())->handle(new Request([
            'name_en' => 'Transportation',
            'balance' => 0,
            'parent_id' => null,
        ]));

        $insertQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'insert into') && str_contains(strtolower($query), 'accounts'));

        $account = Account::query()->where('user_id', $this->user->id)->latest('id')->first();

        $this->assertNotNull($account);
        $this->assertStringContainsString('Transportation', $createOutput);
        $this->assertTrue($insertQueries->isNotEmpty());
        $this->assertFalse($insertQueries->contains(fn (string $query): bool => str_contains(strtolower($query), 'parent_id')));
    }

    public function test_list_accounts_tool_finds_liability_accounts_by_name_without_type_filter(): void
    {
        $creditCard = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Credit Card', 'ar' => null],
            'type' => Account::TYPE_LIABILITY,
            'balance' => 1234.56,
        ]);

        $output = (new ListAccountsTool())->handle(new Request([
            'search' => 'credit card',
        ]));

        $this->assertStringContainsString('Credit Card', $output);
        $this->assertStringContainsString((string) $creditCard->id, $output);
    }

    public function test_list_accounts_tool_filters_out_liability_when_type_asset_is_requested(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Credit Card', 'ar' => null],
            'type' => Account::TYPE_LIABILITY,
        ]);

        $output = (new ListAccountsTool())->handle(new Request([
            'search' => 'credit card',
            'type' => Account::TYPE_ASSET,
        ]));

        $this->assertStringContainsString('No accounts found', $output);
    }

    public function test_budget_tools_can_create_list_and_edit_budgets(): void
    {
        $food = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $transport = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Transport', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $createOutput = (new CreateBudgetTool())->handle(new Request([
            'name_en' => 'Monthly Essentials',
            'amount' => 1200,
            'start_at' => '2026-04-01',
            'period' => 1,
            'reoccurrence' => 'monthly',
            'account_ids' => [$food->id, $transport->id],
        ]));

        $budget = Budget::query()->with('accounts')->latest('id')->first();
        $listOutput = (new ListBudgetsTool())->handle(new Request([
            'reoccurrence' => Budget::MONTHLY,
        ]));
        $editOutput = (new EditBudgetTool())->handle(new Request([
            'budget_id' => $budget->id,
            'amount' => 1500,
            'account_ids' => [$food->id],
            'saving' => true,
        ]));

        $budget->refresh()->load('accounts');

        $this->assertStringContainsString('Monthly Essentials', $createOutput);
        $this->assertStringContainsString((string) $budget->id, $listOutput);
        $this->assertStringContainsString('1500.00', $editOutput);
        $this->assertSame(1500.0, $budget->amount);
        $this->assertTrue($budget->saving);
        $this->assertSame([$food->id], $budget->accounts->pluck('id')->all());
    }

    public function test_transaction_tools_can_list_and_edit_transactions(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Checking', 'ar' => null],
            'type' => Account::TYPE_ASSET,
            'currency' => 'AED',
        ]);
        $expenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $savingsAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => null],
            'type' => Account::TYPE_ASSET,
        ]);
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $sourceAccount->id,
            'category_id' => null,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $expenseAccount->id,
            'amount' => 25,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Lunch',
            'created_at' => '2026-04-12',
        ]);

        $listOutput = (new ListTransactionsTool())->handle(new Request([
            'account_id' => $sourceAccount->id,
        ]));
        $editOutput = (new EditTransactionTool())->handle(new Request([
            'transaction_id' => $transaction->id,
            'amount' => 40,
            'to_account_id' => $savingsAccount->id,
            'note' => 'Savings transfer',
        ]));

        $transaction = Transaction::withoutGlobalScopes()->with(['account', 'category', 'fromAccount', 'toAccount'])->find($transaction->id);

        $this->assertStringContainsString('Lunch', $listOutput);
        $this->assertStringContainsString('Savings transfer', $editOutput);
        $this->assertSame(40.0, $transaction->amount);
        $this->assertSame($sourceAccount->id, $transaction->from_account_id);
        $this->assertSame($savingsAccount->id, $transaction->to_account_id);
        $this->assertNull($transaction->category_id);
        $this->assertSame('AED', $transaction->currency);
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

        $this->assertCount(1, $transactions);
        $this->assertStringContainsString('Transfer created successfully', $output);
        $this->assertSame($sourceAccount->id, $transactions->first()->from_account_id);
        $this->assertSame($destinationAccount->id, $transactions->first()->to_account_id);
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

    public function test_edit_transaction_tool_requires_edit_access_for_replacement_accounts(): void
    {
        $owner = User::factory()->create();
        $sharedAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Shared Wallet', 'ar' => null],
            'type' => Account::TYPE_ASSET,
        ]);
        $sharedAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $initialDestinationAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'type' => Account::TYPE_EXPENSE,
            'name' => ['en' => 'Owner Food', 'ar' => null],
        ]);

        $viewOnlyDestinationAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'type' => Account::TYPE_EXPENSE,
            'name' => ['en' => 'View Only Food', 'ar' => null],
        ]);
        $viewOnlyDestinationAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $sharedAccount->id,
            'category_id' => null,
            'from_account_id' => $sharedAccount->id,
            'to_account_id' => $initialDestinationAccount->id,
            'amount' => 20,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => 'AED',
            'note' => 'Shared lunch',
            'created_at' => '2026-04-12',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to modify transactions for the specified account.');

        (new EditTransactionTool())->handle(new Request([
            'transaction_id' => $transaction->id,
            'to_account_id' => $viewOnlyDestinationAccount->id,
            'note' => 'Editor destination attempt',
        ]));
    }
}
