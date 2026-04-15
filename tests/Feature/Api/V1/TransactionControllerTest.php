<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\TransactionAudit;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
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
            'name' => ['en' => 'Checking'],
        ]);
        $this->expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food', 'ar' => 'طعام'],
            'type' => Category::EXPENSES,
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(401);
    }

    public function test_it_returns_only_transactions_for_accessible_accounts(): void
    {
        Transaction::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
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
                        'canEdit',
                        'account' => ['id', 'name', 'name_translations', 'balance', 'isOwner', 'ownerId', 'ownerName', 'participantUserIds', 'permissionLevel', 'canEditTransactions'],
                        'category' => ['id', 'name', 'name_translations', 'ownerUserId', 'type', 'color', 'icon'],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertSame($this->account->id, $response->json('data.0.account.id'));
    }

    public function test_it_filters_transactions_by_account_and_search_term(): void
    {
        $secondaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings'],
        ]);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'note' => 'Morning coffee',
        ]);

        Transaction::factory()->create([
            'account_id' => $secondaryAccount->id,
            'category_id' => $this->expenseCategory->id,
            'note' => 'Lunch prep',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/transactions?filter[account_id]={$secondaryAccount->id}&filter[search]=Lunch");

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.account.id', $secondaryAccount->id)
            ->assertJsonPath('data.0.note', 'Lunch prep');
    }

    public function test_it_creates_a_transaction_for_an_owned_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 42,
            'created_at' => now()->toDateString(),
            'note' => 'Lunch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('transaction.account.id', $this->account->id)
            ->assertJsonPath('transaction.category.id', $this->expenseCategory->id)
            ->assertJsonPath('transaction.note', 'Lunch')
            ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_DEBIT);
    }

    public function test_it_excludes_soft_deleted_categories_from_transaction_form_options(): void
    {
        $deletedCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Old Groceries'],
            'type' => Category::EXPENSES,
        ]);

        $deletedCategory->delete();

        $response = $this->actingAs($this->user)->getJson('/api/v1/transactions/form-options');

        $response->assertOk();

        $this->assertContains($this->expenseCategory->id, collect($response->json('categories'))->pluck('id'));
        $this->assertNotContains($deletedCategory->id, collect($response->json('categories'))->pluck('id'));
    }

    public function test_it_rejects_soft_deleted_categories_when_creating_transactions(): void
    {
        $deletedCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Archived Food'],
            'type' => Category::EXPENSES,
        ]);

        $deletedCategory->delete();

        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $deletedCategory->id,
            'amount' => 15,
            'created_at' => now()->toDateString(),
            'note' => 'Should fail',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_shows_an_accessible_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'note' => 'Groceries',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('transaction.id', $transaction->id)
            ->assertJsonPath('transaction.account.id', $this->account->id)
            ->assertJsonPath('transaction.category.id', $this->expenseCategory->id)
            ->assertJsonPath('transaction.note', 'Groceries');
    }

    public function test_it_localizes_nested_account_and_category_names_for_the_active_locale(): void
    {
        $this->user->forceFill(['locale' => 'ar'])->save();

        $this->account->update([
            'name' => ['en' => 'Checking', 'ar' => 'الحساب الجاري'],
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'note' => 'Groceries',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('transaction.account.name', 'الحساب الجاري')
            ->assertJsonPath('transaction.account.name_translations.en', 'Checking')
            ->assertJsonPath('transaction.category.name', 'طعام')
            ->assertJsonPath('transaction.category.name_translations.en', 'Food');
    }

    public function test_it_localizes_audit_snapshots_for_the_active_locale(): void
    {
        $this->user->forceFill(['locale' => 'ar'])->save();

        $this->account->update([
            'name' => ['en' => 'Checking', 'ar' => 'الحساب الجاري'],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 42,
            'created_at' => now()->toDateString(),
            'note' => 'Lunch',
        ]);

        $response->assertCreated();

        $audit = TransactionAudit::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame('الحساب الجاري', $audit->new_values['account_name']);
        $this->assertSame('طعام', $audit->new_values['category_name']);
    }

    public function test_it_updates_a_transaction_and_its_account_reference(): void
    {
        $secondaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings'],
        ]);
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 50,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $secondaryAccount->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 75,
            'created_at' => now()->toDateString(),
            'note' => 'Moved',
        ]);

        $response->assertOk()
            ->assertJsonPath('transaction.account.id', $secondaryAccount->id)
            ->assertJsonPath('transaction.amount', 75)
            ->assertJsonPath('transaction.note', 'Moved');
    }

    public function test_it_deletes_a_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('transaction.id', $transaction->id);

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);

        $indexResponse = $this->actingAs($this->user)->getJson('/api/v1/transactions');
        $showResponse = $this->actingAs($this->user)->getJson("/api/v1/transactions/{$transaction->id}");

        $indexResponse->assertOk()
            ->assertJsonPath('paginatorInfo.total', 0);
        $showResponse->assertNotFound();
    }

    public function test_it_returns_404_for_transactions_on_other_users_accounts(): void
    {
        $transaction = Transaction::factory()->create();

        $showResponse = $this->actingAs($this->user)->getJson("/api/v1/transactions/{$transaction->id}");

        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 10,
            'created_at' => now()->toDateString(),
        ]);

        $deleteResponse = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $showResponse->assertNotFound();
        $updateResponse->assertNotFound();
        $deleteResponse->assertNotFound();
    }

    public function test_view_shared_user_can_read_transactions_for_shared_accounts(): void
    {
        $owner = $this->createUser();
        $sharedUser = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Travel'],
            'type' => Category::EXPENSES,
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $indexResponse = $this->actingAs($sharedUser)->getJson('/api/v1/transactions');
        $showResponse = $this->actingAs($sharedUser)->getJson("/api/v1/transactions/{$transaction->id}");

        $indexResponse->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.category.name', 'Travel')
            ->assertJsonPath('data.0.canEdit', false);

        $showResponse->assertOk()
            ->assertJsonPath('transaction.id', $transaction->id)
            ->assertJsonPath('transaction.canEdit', false)
            ->assertJsonPath('transaction.account.ownerName', $owner->name)
            ->assertJsonPath('transaction.account.permissionLevel', Account::PERMISSION_VIEW);
    }

    public function test_view_shared_user_cannot_write_transactions(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($viewer->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Bills'],
            'type' => Category::EXPENSES,
        ]);
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        $createResponse = $this->actingAs($viewer)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 20,
            'created_at' => now()->toDateString(),
        ]);

        $updateResponse = $this->actingAs($viewer)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 55,
            'created_at' => now()->toDateString(),
        ]);

        $deleteResponse = $this->actingAs($viewer)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $createResponse->assertStatus(403);
        $updateResponse->assertStatus(403);
        $deleteResponse->assertStatus(403);
    }

    public function test_edit_shared_user_can_write_transactions(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Food'],
            'type' => Category::EXPENSES,
        ]);

        $createResponse = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 44,
            'created_at' => now()->toDateString(),
            'note' => 'Shared write',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('transaction.account.id', $account->id)
            ->assertJsonPath('transaction.canEdit', true);

        $transactionId = $createResponse->json('transaction.id');

        $updateResponse = $this->actingAs($editor)->putJson("/api/v1/transactions/{$transactionId}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 66,
            'created_at' => now()->toDateString(),
            'note' => 'Updated shared write',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('transaction.note', 'Updated shared write');

        $deleteResponse = $this->actingAs($editor)->deleteJson("/api/v1/transactions/{$transactionId}");

        $deleteResponse->assertOk();
    }

    public function test_shared_user_can_fetch_the_shared_account_owners_categories_when_account_id_is_supplied(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($viewer->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $category = Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Health'],
            'type' => Category::EXPENSES,
        ]);

        $viewerCategory = Category::factory()->create([
            'user_id' => $viewer->id,
            'name' => ['en' => 'Viewer Health'],
            'type' => Category::EXPENSES,
        ]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/transactions/form-options?account_id={$account->id}");

        $response->assertOk();

        $this->assertContains($category->id, collect($response->json('categories'))->pluck('id'));
        $this->assertNotContains($viewerCategory->id, collect($response->json('categories'))->pluck('id'));
    }

    public function test_edit_shared_user_cannot_create_transaction_with_their_own_category_on_a_shared_account(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        Category::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Owner Food'],
            'type' => Category::EXPENSES,
        ]);

        $editorCategory = Category::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Food'],
            'type' => Category::EXPENSES,
        ]);

        $response = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'category_id' => $editorCategory->id,
            'amount' => 22,
            'created_at' => now()->toDateString(),
            'note' => 'Editor category on shared account',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_owner_cannot_create_a_new_transaction_with_a_shared_users_category_on_the_shared_account(): void
    {
        $owner = $this->createUser(['name' => 'Primary Owner']);
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $editorCategory = Category::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Food'],
            'type' => Category::EXPENSES,
        ]);

        $optionsResponse = $this->actingAs($owner)->getJson("/api/v1/transactions/form-options?account_id={$account->id}");

        $optionsResponse->assertOk();
        $this->assertNotContains($editorCategory->id, collect($optionsResponse->json('categories'))->pluck('id'));

        $createResponse = $this->actingAs($owner)->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'category_id' => $editorCategory->id,
            'amount' => 27,
            'created_at' => now()->toDateString(),
            'note' => 'Owner reused shared category',
        ]);

        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }
}
