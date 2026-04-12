<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
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
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
        ]);

        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'EUR',
            'reoccurrence' => Budget::CUSTOM,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $budget->categories()->sync([$category->id]);

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
            'currency' => 'USD',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
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
    }

    public function test_it_creates_a_budget(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

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
                'category_ids' => [$category->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('budget.name', 'Groceries')
            ->assertJsonPath('budget.name_translations.en', 'Groceries')
            ->assertJsonPath('budget.categories.0.id', $category->id)
            ->assertJsonPath('budget.currency', 'EUR');

        $budget = Budget::query()->latest('id')->first();

        $this->assertNotNull($budget);
        $this->assertSame($this->user->id, $budget->user_id);
        $this->assertSame('Groceries', $budget->getTranslation('name', 'en'));
        $this->assertSame([$category->id], $budget->categories()->pluck('categories.id')->all());
        $this->assertSame('EUR', $budget->currency);
    }

    public function test_it_validates_budget_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/budgets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'amount', 'currency', 'start_at', 'period', 'reoccurrence', 'category_ids']);
    }

    public function test_it_updates_a_budget(): void
    {
        $originalCategory = Category::factory()->create(['user_id' => $this->user->id]);
        $newCategory = Category::factory()->create(['user_id' => $this->user->id]);
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Old Budget'],
            'reoccurrence' => Budget::CUSTOM,
            'start_at' => now()->subWeek(),
            'end_at' => now()->addWeek(),
        ]);
        $budget->categories()->sync([$originalCategory->id]);

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
                'category_ids' => [$newCategory->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('budget.name', 'New Budget')
            ->assertJsonPath('budget.saving', true)
            ->assertJsonPath('budget.categories.0.id', $newCategory->id)
            ->assertJsonPath('budget.currency', 'GBP');

        $budget->refresh();

        $this->assertSame('New Budget', $budget->getTranslation('name', 'en'));
        $this->assertTrue($budget->saving);
        $this->assertSame([$newCategory->id], $budget->categories()->pluck('categories.id')->all());
        $this->assertSame('GBP', $budget->currency);
        $this->assertSoftDeleted('budget_category', [
            'budget_id' => $budget->id,
            'category_id' => $originalCategory->id,
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
        $category = Category::factory()->create(['user_id' => $this->user->id]);
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
                'category_ids' => [$category->id],
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
