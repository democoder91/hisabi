<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => Category::EXPENSES]);
        $this->brand = Brand::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);
    }

    public function test_it_creates_transaction_with_explicit_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'amount' => 100,
                'brand_id' => $this->brand->id,
                'created_at' => now()->toDateString(),
                'currency' => 'USD',
            ]);

        $response->assertStatus(201);
        $transaction = Transaction::latest()->first();
        $this->assertEquals('USD', $transaction->currency);
    }

    public function test_it_creates_transaction_with_default_currency_when_not_specified(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'amount' => 100,
                'brand_id' => $this->brand->id,
                'created_at' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
        $transaction = Transaction::latest()->first();
        // Default from migration is 'AED'
        $this->assertEquals('AED', $transaction->currency);
    }

    public function test_it_validates_currency_must_be_3_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/transactions', [
                'account_id' => $this->account->id,
                'amount' => 100,
                'brand_id' => $this->brand->id,
                'created_at' => now()->toDateString(),
                'currency' => 'ABCD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_it_updates_user_profile_with_default_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/profile', [
                'default_currency' => 'EUR',
            ]);

        $response->assertStatus(200);
        $this->user->refresh();
        $this->assertEquals('EUR', $this->user->default_currency);
    }

    public function test_it_clears_user_default_currency(): void
    {
        $this->user->update(['default_currency' => 'GBP']);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/profile', [
                'default_currency' => null,
            ]);

        $response->assertStatus(200);
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

    public function test_transaction_update_preserves_currency(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/transactions/{$transaction->id}", [
                'account_id' => $this->account->id,
                'amount' => 200,
                'brand_id' => $this->brand->id,
                'created_at' => now()->toDateString(),
                'currency' => 'GBP',
            ]);

        $response->assertStatus(200);
        $transaction->refresh();
        $this->assertEquals('GBP', $transaction->currency);
    }
}
