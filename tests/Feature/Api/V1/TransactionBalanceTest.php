<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Account $secondaryAccount;
    private Account $expenseAccount;
    private Account $secondaryExpenseAccount;
    private Account $incomeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 1000]);
        $this->secondaryAccount = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 300]);
        $this->expenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 0,
            'name' => ['en' => 'Food'],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $this->secondaryExpenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 0,
            'name' => ['en' => 'Transport'],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $this->incomeAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'balance' => 0,
            'name' => ['en' => 'Salary'],
            'type' => Account::TYPE_INCOME,
        ]);
    }

    public function test_creating_a_debit_transaction_decreases_the_source_account_balance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 150,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertCreated();

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(850.0, $this->account->balance);
        $this->assertSame(150.0, $this->expenseAccount->balance);
    }

    public function test_creating_a_credit_transaction_increases_the_destination_account_balance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->incomeAccount->id,
            'to_account_id' => $this->account->id,
            'amount' => 200,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertCreated();

        $this->incomeAccount->refresh();
        $this->account->refresh();
        $this->assertSame(200.0, $this->incomeAccount->balance);
        $this->assertSame(1200.0, $this->account->balance);
    }

    public function test_updating_a_transaction_recalculates_the_balance_using_the_difference(): void
    {
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 100,
            'transaction_type' => Transaction::TYPE_DEBIT,
        ]);

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(900.0, $this->account->balance);
        $this->assertSame(100.0, $this->expenseAccount->balance);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 160,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(840.0, $this->account->balance);
        $this->assertSame(160.0, $this->expenseAccount->balance);
    }

    public function test_updating_a_transaction_to_a_different_account_moves_the_balance_effect(): void
    {
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 80,
            'transaction_type' => Transaction::TYPE_DEBIT,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $this->secondaryAccount->id,
            'to_account_id' => $this->secondaryExpenseAccount->id,
            'amount' => 120,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->account->refresh();
        $this->secondaryAccount->refresh();
        $this->expenseAccount->refresh();
        $this->secondaryExpenseAccount->refresh();

        $this->assertSame(1000.0, $this->account->balance);
        $this->assertSame(180.0, $this->secondaryAccount->balance);
        $this->assertSame(0.0, $this->expenseAccount->balance);
        $this->assertSame(120.0, $this->secondaryExpenseAccount->balance);
    }

    public function test_deleting_a_transaction_reverses_its_balance_effect(): void
    {
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 125,
            'transaction_type' => Transaction::TYPE_DEBIT,
        ]);

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(875.0, $this->account->balance);
        $this->assertSame(125.0, $this->expenseAccount->balance);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk();

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(1000.0, $this->account->balance);
        $this->assertSame(0.0, $this->expenseAccount->balance);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_restoring_a_transaction_reapplies_its_balance_effect(): void
    {
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 125,
            'transaction_type' => Transaction::TYPE_DEBIT,
        ]);

        $transaction->delete();

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(1000.0, $this->account->balance);
        $this->assertSame(0.0, $this->expenseAccount->balance);

        $transaction->restore();

        $this->account->refresh();
        $this->expenseAccount->refresh();
        $this->assertSame(875.0, $this->account->balance);
        $this->assertSame(125.0, $this->expenseAccount->balance);
    }
}