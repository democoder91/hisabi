<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'EUR',
        ]);
        $this->expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
        ]);
    }

    public function test_it_creates_transaction_with_the_selected_account_currency_even_when_currency_is_sent(): void
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
        $this->assertSame('EUR', $transaction->currency);
    }

    public function test_it_creates_transaction_with_the_selected_account_currency_when_currency_is_not_sent(): void
    {
        $this->account->update(['currency' => 'AED']);

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

    public function test_transaction_update_syncs_currency_to_the_updated_account(): void
    {
        $secondAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'GBP',
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/transactions/{$transaction->id}", [
                'account_id' => $secondAccount->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 200,
                'created_at' => now()->toDateString(),
                'currency' => 'CAD',
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

    public function test_it_returns_settings_with_currency_options(): void
    {
        $this->user->update(['default_currency' => 'EUR']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonPath('settings.default_currency', 'EUR')
            ->assertJsonPath('settings.effective_currency', 'EUR')
            ->assertJsonPath('defaults.currency', 'EGP')
            ->assertJsonFragment(['value' => 'USD', 'label' => 'USD']);
    }

    public function test_it_updates_settings_default_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'default_currency' => 'usd',
            ]);

        $response->assertOk()
            ->assertJsonPath('settings.default_currency', 'USD')
            ->assertJsonPath('settings.effective_currency', 'USD');

        $this->user->refresh();
        $this->assertSame('USD', $this->user->default_currency);
    }

    public function test_it_rejects_unsupported_settings_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'default_currency' => 'XYZ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_currency']);
    }

    public function test_it_returns_currency_settings_with_rates(): void
    {
        $this->user->update(['default_currency' => 'GBP']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings/currencies');

        $response->assertOk()
            ->assertJsonPath('settings.default_currency', 'GBP')
            ->assertJsonPath('settings.effective_currency', 'GBP')
            ->assertJsonPath('defaults.currency', config('hisabi.currency'));

        $rates = collect($response->json('rates'));

        $this->assertSame(1.0, (float) $rates->firstWhere('currency', 'USD')['rate']);
        $this->assertNotNull($rates->firstWhere('currency', 'EUR'));
    }

    public function test_it_updates_currency_settings_default_currency(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings/currencies', [
                'default_currency' => 'sar',
            ]);

        $response->assertOk()
            ->assertJsonPath('settings.default_currency', 'SAR')
            ->assertJsonPath('settings.effective_currency', 'SAR');

        $this->user->refresh();
        $this->assertSame('SAR', $this->user->default_currency);
    }

    public function test_it_updates_currency_rates_manually(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings/currencies/rates', [
                'rates' => [
                    ['currency' => 'eur', 'rate' => 0.92],
                    ['currency' => 'gbp', 'rate' => 0.78],
                ],
            ]);

        $response->assertOk();

        $eurRate = ExchangeRate::query()
            ->where('user_id', $this->user->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $usdRate = ExchangeRate::query()
            ->where('user_id', $this->user->id)
            ->where('currency', 'USD')
            ->firstOrFail();

        $this->assertSame(0.92, $eurRate->rate);
        $this->assertSame('manual', $eurRate->source);
        $this->assertSame(1.0, $usdRate->rate);
    }

    public function test_it_refreshes_currency_rates_from_the_provider(): void
    {
        Http::fake([
            '*' => Http::response([
                'rates' => [
                    'EUR' => 0.91,
                    'GBP' => 0.79,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/settings/currencies/refresh');

        $response->assertOk();

        $eurRate = ExchangeRate::query()
            ->where('user_id', $this->user->id)
            ->where('currency', 'EUR')
            ->firstOrFail();

        $usdRate = ExchangeRate::query()
            ->where('user_id', $this->user->id)
            ->where('currency', 'USD')
            ->firstOrFail();

        $this->assertSame(0.91, $eurRate->rate);
        $this->assertSame('api', $eurRate->source);
        $this->assertSame(1.0, $usdRate->rate);
        $this->assertNotNull($response->json('last_refreshed_at'));
    }
}
