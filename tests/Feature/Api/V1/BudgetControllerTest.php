<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Transaction\Models\Transaction;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $trackingAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->trackingAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Groceries'],
            'type' => Account::TYPE_EXPENSE,
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/budgets');
        $response->assertStatus(401);
    }

    public function test_it_returns_all_budgets(): void
    {
        Budget::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/budgets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_translations',
                        'amount',
                        'start_at',
                        'end_at',
                        'saving',
                        'period',
                        'reoccurrence',
                        'total_spent_percentage',
                        'start_at_date',
                        'end_at_date',
                        'remaining_to_spend',
                        'total_margin_per_day',
                        'remaining_days',
                        'elapsed_days_percentage',
                        'is_saving',
                        'total_transactions_amount',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_returns_empty_array_when_no_budgets(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/budgets');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    public function test_it_returns_budget_with_computed_fields(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Test Budget', 'ar' => 'ميزانية اختبار'],
            'amount' => 1000,
            'start_at' => now()->subDays(10),
            'reoccurrence' => Budget::MONTHLY,
            'period' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/budgets');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Test Budget')
            ->assertJsonPath('data.0.amount', 1000);
    }

    public function test_it_converts_matching_transactions_into_the_budget_currency(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'EUR',
            'reoccurrence' => Budget::CUSTOM,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $budget->accounts()->sync([$this->trackingAccount->id]);

        ExchangeRate::query()->updateOrCreate(
            ['user_id' => $this->user->id, 'currency' => 'USD'],
            ['rate' => 1, 'source' => 'manual', 'last_synced_at' => now()],
        );

        ExchangeRate::query()->updateOrCreate(
            ['user_id' => $this->user->id, 'currency' => 'EUR'],
            ['rate' => 0.8, 'source' => 'manual', 'last_synced_at' => now()],
        );

        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_ASSET,
            'currency' => 'USD',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => null,
            'from_account_id' => $account->id,
            'to_account_id' => $this->trackingAccount->id,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'amount' => 100,
            'created_at' => now(),
        ]);

        $budget->refresh();

        $this->assertSame(80.0, $budget->total_transactions_amount);
    }

    public function test_it_shows_a_budget(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Budget', 'ar' => 'ميزانية الطوارئ'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonPath('budget.id', $budget->id)
            ->assertJsonPath('budget.name', 'Emergency Budget')
            ->assertJsonPath('budget.name_translations.ar', 'ميزانية الطوارئ');

        $this->assertArrayNotHasKey('categories', $response->json('budget'));
    }

    public function test_it_returns_localized_budget_and_account_names_for_the_active_locale(): void
    {
        $this->user->forceFill(['locale' => 'ar'])->save();

        $this->trackingAccount->update([
            'name' => ['en' => 'Groceries', 'ar' => 'البقالة'],
        ]);

        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Emergency Budget', 'ar' => 'ميزانية الطوارئ'],
        ]);

        $budget->accounts()->sync([$this->trackingAccount->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonPath('budget.name', 'ميزانية الطوارئ')
            ->assertJsonPath('budget.accounts.0.name', 'البقالة')
            ->assertJsonPath('budget.accounts.0.name_translations.en', 'Groceries');

        $this->assertArrayNotHasKey('categories', $response->json('budget'));
    }

    public function test_it_creates_a_budget(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/budgets', [
                'name' => ['en' => 'Groceries', 'ar' => 'مشتريات'],
                'amount' => 1500,
                'currency' => 'eur',
                'start_at' => now()->startOfMonth()->toDateString(),
                'end_at' => now()->endOfMonth()->toDateString(),
                'saving' => false,
                'period' => 1,
                'reoccurrence' => Budget::CUSTOM,
                'account_ids' => [$this->trackingAccount->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('budget.name', 'Groceries')
            ->assertJsonPath('budget.name_translations.en', 'Groceries')
            ->assertJsonPath('budget.accounts.0.id', $this->trackingAccount->id)
            ->assertJsonPath('budget.currency', 'EUR');

        $budget = Budget::query()->latest('id')->first();

        $this->assertNotNull($budget);
        $this->assertSame($this->user->id, $budget->user_id);
        $this->assertSame('Groceries', $budget->getTranslation('name', 'en'));
        $this->assertSame([$this->trackingAccount->id], $budget->accounts()->pluck('accounts.id')->all());
        $this->assertSame('EUR', $budget->currency);
        $this->assertDatabaseHas('budget_account', [
            'budget_id' => $budget->id,
            'account_id' => $this->trackingAccount->id,
        ]);
    }

    public function test_it_validates_budget_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/budgets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'amount', 'currency', 'start_at', 'period', 'reoccurrence', 'account_ids']);
    }

    public function test_it_requires_account_ids_when_legacy_category_ids_are_supplied(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/budgets', [
                'name' => ['en' => 'Groceries', 'ar' => 'مشتريات'],
                'amount' => 1500,
                'currency' => 'USD',
                'start_at' => now()->startOfMonth()->toDateString(),
                'end_at' => now()->endOfMonth()->toDateString(),
                'saving' => false,
                'period' => 1,
                'reoccurrence' => Budget::CUSTOM,
                'category_ids' => [9999],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_ids']);
    }

    public function test_it_updates_a_budget(): void
    {
        $newAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Transport'],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Old Budget'],
            'reoccurrence' => Budget::CUSTOM,
            'start_at' => now()->subWeek(),
            'end_at' => now()->addWeek(),
        ]);
        $budget->accounts()->sync([$this->trackingAccount->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/budgets/{$budget->id}", [
                'name' => ['en' => 'New Budget', 'ar' => 'ميزانية جديدة'],
                'amount' => 2200,
                'currency' => 'gbp',
                'start_at' => now()->startOfMonth()->toDateString(),
                'end_at' => now()->endOfMonth()->toDateString(),
                'saving' => true,
                'period' => 1,
                'reoccurrence' => Budget::CUSTOM,
                'account_ids' => [$newAccount->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('budget.name', 'New Budget')
            ->assertJsonPath('budget.saving', true)
            ->assertJsonPath('budget.accounts.0.id', $newAccount->id)
            ->assertJsonPath('budget.currency', 'GBP');

        $budget->refresh();

        $this->assertSame('New Budget', $budget->getTranslation('name', 'en'));
        $this->assertTrue($budget->saving);
        $this->assertSame([$newAccount->id], $budget->accounts()->pluck('accounts.id')->all());
        $this->assertSame('GBP', $budget->currency);
        $this->assertSoftDeleted('budget_account', [
            'budget_id' => $budget->id,
            'account_id' => $this->trackingAccount->id,
        ]);
    }

    public function test_it_deletes_a_budget(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Budget to Delete'],
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonPath('budget.id', $budget->id)
            ->assertJsonPath('budget.name', 'Budget to Delete');

        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
    }

    public function test_it_returns_404_when_updating_another_users_budget(): void
    {
        $budget = Budget::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/budgets/{$budget->id}", [
                'name' => ['en' => 'Should Fail'],
                'amount' => 100,
                'currency' => 'USD',
                'start_at' => now()->toDateString(),
                'end_at' => now()->addDay()->toDateString(),
                'saving' => false,
                'period' => 1,
                'reoccurrence' => Budget::CUSTOM,
                'account_ids' => [$this->trackingAccount->id],
            ]);

        $response->assertNotFound();
    }

    public function test_it_returns_404_when_showing_another_users_budget(): void
    {
        $budget = Budget::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/budgets/{$budget->id}");

        $response->assertNotFound();
    }

    public function test_it_returns_404_when_deleting_another_users_budget(): void
    {
        $budget = Budget::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/budgets/{$budget->id}");

        $response->assertNotFound();
    }

    public function test_it_only_returns_budgets_owned_by_the_authenticated_user(): void
    {
        Budget::factory()->count(2)->create(['user_id' => $this->user->id]);
        Budget::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/budgets');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
