<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSharedAccountContext;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use CreatesSharedAccountContext;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/categories/all');
        $response->assertStatus(401);
    }

    public function test_it_returns_all_categories(): void
    {
        Category::factory()->count(10)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/categories/all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_translations',
                        'type',
                        'color',
                        'icon',
                        'transactionsCount',
                    ],
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_it_includes_transactions_count(): void
    {
        Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/categories/all');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.transactionsCount', 0);
    }

    public function test_it_requires_authentication_for_store(): void
    {
        $response = $this->postJson('/api/v1/categories', []);
        $response->assertStatus(401);
    }

    public function test_it_creates_a_category(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/categories', [
                'name' => ['en' => 'Test Category', 'ar' => 'فئة تجريبية'],
                'type' => 'EXPENSES',
                'color' => 'red',
                'icon' => 'wallet',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'category' => [
                    'id',
                    'name',
                    'user_id',
                    'type',
                    'color',
                    'icon',
                    'transactions_count',
                ],
            ])
            ->assertJsonPath('category.name.en', 'Test Category')
            ->assertJsonPath('category.type', 'EXPENSES')
            ->assertJsonPath('category.color', 'red')
            ->assertJsonPath('category.icon', 'wallet');

        $category = Category::query()->latest('id')->first();

        $this->assertNotNull($category);
        $this->assertSame($this->user->id, $category->user_id);
        $this->assertSame('Test Category', $category->getTranslation('name', 'en'));
    }

    public function test_it_validates_required_fields_for_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'color', 'icon']);
    }

    public function test_it_validates_type_is_valid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/categories', [
                'name' => ['en' => 'Test Category'],
                'type' => 'INVALID_TYPE',
                'color' => 'red',
                'icon' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_it_accepts_all_valid_category_types(): void
    {
        $types = ['INCOME', 'EXPENSES', 'SAVINGS', 'INVESTMENT'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)
                ->postJson('/api/v1/categories', [
                    'name' => ['en' => "Test Category {$type}"],
                    'type' => $type,
                    'color' => 'blue',
                    'icon' => 'wallet',
                ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('categories', 4);
    }

    public function test_it_requires_authentication_for_update(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/categories/{$category->id}", []);
        $response->assertStatus(401);
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Old Name'],
            'type' => 'EXPENSES',
            'color' => 'red',
            'icon' => 'wallet',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => ['en' => 'New Name', 'ar' => 'اسم جديد'],
                'type' => 'INCOME',
                'color' => 'blue',
                'icon' => 'money',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'category' => [
                    'id',
                    'name',
                    'user_id',
                    'type',
                    'color',
                    'icon',
                    'transactions_count',
                ],
            ])
            ->assertJsonPath('category.name.en', 'New Name')
            ->assertJsonPath('category.type', 'INCOME')
            ->assertJsonPath('category.color', 'blue')
            ->assertJsonPath('category.icon', 'money');

        $category->refresh();
        $this->assertSame('New Name', $category->getTranslation('name', 'en'));
        $this->assertSame($this->user->id, $category->user_id);
    }

    public function test_it_validates_required_fields_for_update(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/categories/{$category->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'color', 'icon']);
    }

    public function test_it_validates_type_is_valid_for_update(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => 'Test Category',
                'type' => 'INVALID_TYPE',
                'color' => 'red',
                'icon' => 'wallet',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_it_returns_404_for_non_existent_category(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/categories/999', [
                'name' => ['en' => 'Test Category'],
                'type' => 'EXPENSES',
                'color' => 'red',
                'icon' => 'wallet',
            ]);

        $response->assertStatus(404);
    }

    public function test_it_requires_authentication_for_destroy(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");
        $response->assertStatus(401);
    }

    public function test_it_deletes_a_category(): void
    {
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Category to Delete'],
            'type' => 'EXPENSES',
            'color' => 'red',
            'icon' => 'wallet',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'category' => [
                    'id',
                    'name',
                    'type',
                    'color',
                    'icon',
                ],
            ])
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('category.name.en', 'Category to Delete');

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_it_returns_404_for_non_existent_category_on_delete(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/categories/999');

        $response->assertStatus(404);
    }

    public function test_it_only_returns_categories_owned_by_the_authenticated_user(): void
    {
        Category::factory()->count(2)->create(['user_id' => $this->user->id]);
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/categories/all');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_it_returns_404_when_updating_another_users_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => ['en' => 'Blocked'],
                'type' => 'EXPENSES',
                'color' => 'red',
                'icon' => 'wallet',
            ]);

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_deleting_another_users_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(404);
    }

    public function test_shared_users_do_not_see_owner_categories_via_generic_category_endpoints(): void
    {
        $context = $this->createSharedAccountContext(Account::PERMISSION_VIEW);

        $response = $this->actingAs($context['sharedUser'])
            ->getJson('/api/v1/categories/all');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }
}
