<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Checking',
        ]);

        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food'],
            'type' => Category::EXPENSES,
        ]);

        $this->brand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Cafe'],
            'category_id' => $category->id,
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(401);
    }

    public function test_it_returns_only_transactions_for_owned_accounts(): void
    {
        Transaction::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);

        Transaction::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 3)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'transaction_type',
                        'created_at',
                        'note',
                        'account' => ['id', 'name', 'balance'],
                        'brand' => [
                            'id',
                            'name',
                            'category' => ['id', 'name', 'type', 'color', 'icon'],
                        ],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertSame($this->account->id, $response->json('data.0.account.id'));
    }

    public function test_it_returns_transactions_sorted_by_id_descending(): void
    {
        $first = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);
        $second = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);
        $third = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/transactions');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame($third->id, $data[0]['id']);
        $this->assertSame($second->id, $data[1]['id']);
        $this->assertSame($first->id, $data[2]['id']);
    }

    public function test_it_paginates_and_filters_by_account(): void
    {
        $secondary = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Savings',
        ]);

        Transaction::factory()->count(12)->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);
        Transaction::factory()->count(4)->create([
            'account_id' => $secondary->id,
            'brand_id' => $this->brand->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/transactions?perPage=10&filter[account_id]={$secondary->id}");

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 4)
            ->assertJsonPath('paginatorInfo.perPage', 10);

        $this->assertCount(4, $response->json('data'));
    }

    public function test_it_searches_by_amount_note_and_brand_name(): void
    {
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 25.5,
            'note' => 'Morning coffee',
        ]);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 500,
            'note' => 'Groceries',
        ]);

        $byAmount = $this->actingAs($this->user)->getJson('/api/v1/transactions?filter[search]=25.5');
        $byAmount->assertOk();
        $this->assertCount(1, $byAmount->json('data'));

        $byNote = $this->actingAs($this->user)->getJson('/api/v1/transactions?filter[search]=coffee');
        $byNote->assertOk();
        $this->assertCount(1, $byNote->json('data'));

        $byBrand = $this->actingAs($this->user)->getJson('/api/v1/transactions?filter[search]=cafe');
        $byBrand->assertOk();
        $this->assertCount(2, $byBrand->json('data'));
    }

    public function test_it_creates_a_transaction_for_an_owned_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'amount' => 42,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Lunch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('transaction.account.id', $this->account->id)
            ->assertJsonPath('transaction.brand.name', 'Cafe')
            ->assertJsonPath('transaction.note', 'Lunch');
    }

    public function test_it_updates_a_transaction_and_its_account_reference(): void
    {
        $secondary = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Savings',
        ]);
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 50,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $secondary->id,
            'amount' => 75,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Moved',
        ]);

        $response->assertOk()
            ->assertJsonPath('transaction.account.id', $secondary->id)
            ->assertJsonPath('transaction.amount', 75)
            ->assertJsonPath('transaction.note', 'Moved');
    }

    public function test_it_deletes_a_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('transaction.id', $transaction->id);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_it_returns_404_for_transactions_on_other_users_accounts(): void
    {
        $transaction = Transaction::factory()->create();

        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'amount' => 10,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
        ]);

        $deleteResponse = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $updateResponse->assertStatus(404);
        $deleteResponse->assertStatus(404);
    }

    public function test_view_shared_user_can_read_transactions_for_shared_accounts(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Travel'],
            'type' => Category::EXPENSES,
        ]);
        $brand = Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Airline'],
            'category_id' => $category->id,
        ]);

        Transaction::factory()->count(2)->create([
            'account_id' => $account->id,
            'brand_id' => $brand->id,
        ]);

        $response = $this->actingAs($sharedUser)->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 2)
            ->assertJsonPath('data.0.brand.name', 'Airline')
            ->assertJsonPath('data.0.brand.category.name', 'Travel')
            ->assertJsonPath('data.0.canEdit', false);
    }

    public function test_view_shared_user_cannot_write_transactions(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($viewer->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Bills'],
            'type' => Category::EXPENSES,
        ]);
        $brand = Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Utility'],
            'category_id' => $category->id,
        ]);
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'brand_id' => $brand->id,
        ]);

        $createResponse = $this->actingAs($viewer)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'amount' => 20,
            'brand_id' => $brand->id,
            'created_at' => now()->toDateString(),
        ]);

        $updateResponse = $this->actingAs($viewer)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'amount' => 55,
            'brand_id' => $brand->id,
            'created_at' => now()->toDateString(),
        ]);

        $deleteResponse = $this->actingAs($viewer)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $createResponse->assertStatus(403);
        $updateResponse->assertStatus(403);
        $deleteResponse->assertStatus(403);
    }

    public function test_edit_shared_user_can_write_transactions(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Food'],
            'type' => Category::EXPENSES,
        ]);
        $brand = Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Shared Cafe'],
            'category_id' => $category->id,
        ]);

        $createResponse = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'amount' => 44,
            'brand_id' => $brand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Shared write',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('transaction.account.id', $account->id)
            ->assertJsonPath('transaction.canEdit', true);

        $transactionId = $createResponse->json('transaction.id');

        $updateResponse = $this->actingAs($editor)->putJson("/api/v1/transactions/{$transactionId}", [
            'account_id' => $account->id,
            'amount' => 66,
            'brand_id' => $brand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Updated shared write',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('transaction.note', 'Updated shared write');

        $deleteResponse = $this->actingAs($editor)->deleteJson("/api/v1/transactions/{$transactionId}");

        $deleteResponse->assertOk();
    }

    public function test_shared_user_can_fetch_transaction_form_options_for_shared_account_owners(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($viewer->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Health'],
            'type' => Category::EXPENSES,
        ]);
        $brand = Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Pharmacy'],
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/transactions/form-options');

        $response->assertOk();

        $this->assertContains($brand->id, collect($response->json('brands'))->pluck('id'));
        $this->assertContains($category->id, collect($response->json('categories'))->pluck('id'));
    }

    public function test_edit_shared_user_cannot_create_transaction_with_a_brand_not_owned_by_the_account_owner(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $ownerCategory = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Food'],
            'type' => Category::EXPENSES,
        ]);
        Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Owner Brand'],
            'category_id' => $ownerCategory->id,
        ]);

        $editorCategory = Category::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Food'],
            'type' => Category::EXPENSES,
        ]);
        $editorBrand = Brand::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Brand'],
            'category_id' => $editorCategory->id,
        ]);

        $response = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'amount' => 22,
            'brand_id' => $editorBrand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Invalid shared brand',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id']);
    }
}
