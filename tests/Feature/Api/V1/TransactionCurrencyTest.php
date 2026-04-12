<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        $this->expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
        ]);
    }

    public function test_it_creates_transaction_with_explicit_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 100,
                'created_at' => now()->toDateString(),
                'currency' => 'USD',
            ]);

        $response->assertCreated();
        $transaction = Transaction::latest('id')->firstOrFail();
        $this->assertSame('USD', $transaction->currency);
    }

    public function test_it_creates_transaction_with_default_currency_when_not_specified(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 100,
                'created_at' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $transaction = Transaction::latest('id')->firstOrFail();
        $this->assertSame('AED', $transaction->currency);
    }

    public function test_it_validates_currency_must_be_3_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 100,
                'created_at' => now()->toDateString(),
                'currency' => 'ABCD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_transaction_update_preserves_currency_changes(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/transactions/{$transaction->id}", [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 200,
                'created_at' => now()->toDateString(),
                'currency' => 'GBP',
            ]);

        $response->assertOk();
        $transaction->refresh();
        $this->assertSame('GBP', $transaction->currency);
    }

    public function test_it_updates_user_profile_with_default_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/profile', [
                'default_currency' => 'EUR',
            ]);

        $response->assertOk();
        $this->user->refresh();
        $this->assertSame('EUR', $this->user->default_currency);
    }

    public function test_it_clears_user_default_currency(): void
    {
        $this->user->update(['default_currency' => 'GBP']);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/profile', [
                'default_currency' => null,
            ]);

        $response->assertOk();
        $this->user->refresh();
        $this->assertNull($this->user->default_currency);
    }

    public function test_it_validates_default_currency_must_be_3_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/profile', [
                'default_currency' => 'ABCD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_currency']);
    }
}
