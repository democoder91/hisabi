<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
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
    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 1000]);
        $this->secondaryAccount = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 300]);

        $this->expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Food'],
        ]);
    }

    public function test_creating_a_debit_transaction_decreases_the_account_balance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 150,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertCreated();

        $this->account->refresh();
        $this->assertSame(850.0, $this->account->balance);
    }

    public function test_creating_a_credit_transaction_increases_the_account_balance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => Category::factory()->create([
                'user_id' => $this->user->id,
                'type' => Category::INCOME,
                'name' => ['en' => 'Salary'],
            ])->id,
            'amount' => 200,
            'transaction_type' => Transaction::TYPE_CREDIT,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertCreated();

        $this->account->refresh();
        $this->assertSame(1200.0, $this->account->balance);
    }

    public function test_updating_a_transaction_recalculates_the_balance_using_the_difference(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 100,
        ]);

        $this->account->refresh();
        $this->assertSame(900.0, $this->account->balance);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 160,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->account->refresh();
        $this->assertSame(840.0, $this->account->balance);
    }

    public function test_updating_a_transaction_to_a_different_account_moves_the_balance_effect(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 80,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->secondaryAccount->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 120,
            'created_at' => now()->toDateString(),
        ]);

        $response->assertOk();

        $this->account->refresh();
        $this->secondaryAccount->refresh();

        $this->assertSame(1000.0, $this->account->balance);
        $this->assertSame(180.0, $this->secondaryAccount->balance);
    }

    public function test_deleting_a_transaction_reverses_its_balance_effect(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 125,
        ]);

        $this->account->refresh();
        $this->assertSame(875.0, $this->account->balance);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk();

        $this->account->refresh();
        $this->assertSame(1000.0, $this->account->balance);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_restoring_a_transaction_reapplies_its_balance_effect(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 125,
        ]);

        $transaction->delete();

        $this->account->refresh();
        $this->assertSame(1000.0, $this->account->balance);

        $transaction->restore();

        $this->account->refresh();
        $this->assertSame(875.0, $this->account->balance);
    }
}