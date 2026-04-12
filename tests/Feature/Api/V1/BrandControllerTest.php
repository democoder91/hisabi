<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSharedAccountContext;
use Tests\TestCase;

class BrandControllerTest extends TestCase
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
        $response = $this->getJson('/api/v1/brands');
        $response->assertStatus(401);
    }

    public function test_it_returns_paginated_brands(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Brand::factory()->count(10)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/brands');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_translations',
                        'category' => [
                            'id',
                            'name',
                            'name_translations',
                            'color',
                            'icon',
                        ],
                        'transactionsCount',
                    ],
                ],
                'paginatorInfo' => [
                    'hasMorePages',
                    'currentPage',
                    'lastPage',
                    'perPage',
                    'total',
                ],
            ]);

        $this->assertEquals(10, $response->json('paginatorInfo.total'));
    }

    public function test_it_filters_brands_by_search(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'name' => ['en' => 'Food']]);
        $brand1 = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'McDonald'],
            'category_id' => $category->id
        ]);
        $brand2 = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Starbucks'],
            'category_id' => $category->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/brands?filter[search]=McDonald');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('paginatorInfo.total'));
        $this->assertEquals('McDonald', $response->json('data.0.name'));
    }

    public function test_it_filters_brands_by_category(): void
    {
        $category1 = Category::factory()->create(['user_id' => $this->user->id]);
        $category2 = Category::factory()->create(['user_id' => $this->user->id]);

        $brand1 = Brand::factory()->create(['user_id' => $this->user->id, 'category_id' => $category1->id]);
        $brand2 = Brand::factory()->create(['user_id' => $this->user->id, 'category_id' => $category2->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/brands?filter[category_id]={$category1->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('paginatorInfo.total'));
        $this->assertEquals($brand1->id, $response->json('data.0.id'));
    }

    public function test_it_includes_transactions_count(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        $brand = Brand::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/brands');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.transactionsCount', 0);
    }

    public function test_it_respects_per_page_parameter(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Brand::factory()->count(30)->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/brands?perPage=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('paginatorInfo.perPage'));
        $this->assertEquals(10, count($response->json('data')));
    }

    public function test_it_requires_authentication_for_store(): void
    {
        $response = $this->postJson('/api/v1/brands', []);
        $response->assertStatus(401);
    }

    public function test_it_creates_a_brand(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/brands', [
                'name' => ['en' => 'Test Brand', 'ar' => 'علامة تجريبية'],
                'category_id' => $category->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'brand' => [
                    'id',
                    'name',
                    'category' => [
                        'id',
                        'name',
                    ],
                    'transactions_count',
                ],
            ])
            ->assertJsonPath('brand.name.en', 'Test Brand')
            ->assertJsonPath('brand.category.id', $category->id);

        $brand = Brand::query()->latest('id')->first();

        $this->assertNotNull($brand);
        $this->assertSame($this->user->id, $brand->user_id);
        $this->assertSame('Test Brand', $brand->getTranslation('name', 'en'));
    }

    public function test_it_validates_required_fields_for_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/brands', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id']);
    }

    public function test_it_validates_category_exists_for_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/brands', [
                'name' => ['en' => 'Test Brand'],
                'category_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_requires_authentication_for_update(): void
    {
        $brand = Brand::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/brands/{$brand->id}", []);
        $response->assertStatus(401);
    }

    public function test_it_updates_a_brand(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        $newCategory = Category::factory()->create(['user_id' => $this->user->id]);
        $brand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Old Name'],
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/brands/{$brand->id}", [
                'name' => ['en' => 'New Name', 'ar' => 'اسم جديد'],
                'category_id' => $newCategory->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'brand' => [
                    'id',
                    'name',
                    'category' => [
                        'id',
                        'name',
                    ],
                    'transactions_count',
                ],
            ])
            ->assertJsonPath('brand.name.en', 'New Name')
            ->assertJsonPath('brand.category.id', $newCategory->id);

        $brand->refresh();
        $this->assertSame('New Name', $brand->getTranslation('name', 'en'));
        $this->assertSame($this->user->id, $brand->user_id);
    }

    public function test_it_validates_required_fields_for_update(): void
    {
        $brand = Brand::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/brands/{$brand->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id']);
    }

    public function test_it_validates_category_exists_for_update(): void
    {
        $brand = Brand::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/brands/{$brand->id}", [
                'name' => 'Test Brand',
                'category_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_returns_404_for_non_existent_brand(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/brands/999', [
                'name' => ['en' => 'Test Brand'],
                'category_id' => $category->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_it_requires_authentication_for_destroy(): void
    {
        $brand = Brand::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/brands/{$brand->id}");
        $response->assertStatus(401);
    }

    public function test_it_deletes_a_brand(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        $brand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Brand to Delete'],
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/brands/{$brand->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'brand' => [
                    'id',
                    'name',
                    'category' => [
                        'id',
                        'name',
                    ],
                ],
            ])
            ->assertJsonPath('brand.id', $brand->id)
            ->assertJsonPath('brand.name.en', 'Brand to Delete');

        $this->assertDatabaseMissing('brands', [
            'id' => $brand->id,
        ]);
    }

    public function test_it_returns_404_for_non_existent_brand_on_delete(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/brands/999');

        $response->assertStatus(404);
    }

    public function test_it_only_returns_brands_owned_by_the_authenticated_user(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);
        Brand::factory()->count(2)->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

        $otherCategory = Category::factory()->create();
        Brand::factory()->count(3)->create(['category_id' => $otherCategory->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/brands');

        $response->assertOk();
        $this->assertSame(2, $response->json('paginatorInfo.total'));
    }

    public function test_it_rejects_creating_a_brand_for_another_users_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/brands', [
                'name' => ['en' => 'Blocked Brand'],
                'category_id' => $category->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_returns_404_when_updating_another_users_brand(): void
    {
        $brand = Brand::factory()->create();
        $ownedCategory = Category::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/brands/{$brand->id}", [
                'name' => ['en' => 'Blocked Brand'],
                'category_id' => $ownedCategory->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_deleting_another_users_brand(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/brands/{$brand->id}");

        $response->assertStatus(404);
    }

    public function test_shared_users_do_not_see_owner_brands_via_generic_brand_endpoints(): void
    {
        $context = $this->createSharedAccountContext(Account::PERMISSION_VIEW);

        $response = $this->actingAs($context['sharedUser'])
            ->getJson('/api/v1/brands');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 0);

        $allResponse = $this->actingAs($context['sharedUser'])
            ->getJson('/api/v1/brands/all');

        $allResponse->assertOk();
        $this->assertEmpty($allResponse->json('data'));
    }
}
