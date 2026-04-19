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

    private Account $expenseAccount;

    private Account $incomeAccount;

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
        $this->expenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food Expense'],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $this->incomeAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Salary'],
            'type' => Account::TYPE_INCOME,
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
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertSame($this->account->id, $response->json('data.0.account.id'));
            $this->assertArrayNotHasKey('category', $response->json('data.0'));
    }

    public function test_it_filters_transactions_by_any_involved_account_and_search_term(): void
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
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $secondaryAccount->id,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'note' => 'Lunch prep',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/transactions?filter[account_id]={$secondaryAccount->id}&filter[search]=Lunch");

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.fromAccount.id', $this->account->id)
            ->assertJsonPath('data.0.toAccount.id', $secondaryAccount->id)
            ->assertJsonPath('data.0.note', 'Lunch prep');
    }

    public function test_it_filters_transactions_by_source_and_destination_accounts(): void
    {
        $otherSourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Cash Wallet'],
        ]);

        $otherDestinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Transport Expense'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'note' => 'Groceries run',
        ]);

        Transaction::factory()->create([
            'account_id' => $otherSourceAccount->id,
            'category_id' => null,
            'from_account_id' => $otherSourceAccount->id,
            'to_account_id' => $otherDestinationAccount->id,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'note' => 'Fuel stop',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/v1/transactions?filter[from_account_id]={$this->account->id}&filter[to_account_id]={$this->expenseAccount->id}"
        );

        $response->assertOk()
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.fromAccount.id', $this->account->id)
            ->assertJsonPath('data.0.toAccount.id', $this->expenseAccount->id)
            ->assertJsonPath('data.0.note', 'Groceries run');
    }

    public function test_it_creates_a_credit_transaction_between_owned_accounts(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->incomeAccount->id,
            'to_account_id' => $this->account->id,
            'amount' => 42,
            'created_at' => now()->toDateString(),
            'note' => 'Salary payout',
        ]);

        $response->assertCreated()
            ->assertJsonPath('transaction.account.id', $this->account->id)
            ->assertJsonPath('transaction.fromAccount.id', $this->incomeAccount->id)
            ->assertJsonPath('transaction.toAccount.id', $this->account->id)
            ->assertJsonPath('transaction.note', 'Salary payout')
            ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_CREDIT);

            $this->assertArrayNotHasKey('category', $response->json('transaction'));
    }

    public function test_it_creates_an_account_to_account_transaction_without_a_category(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 42,
            'created_at' => now()->toDateString(),
            'note' => 'Lunch',
        ]);

        $response->assertCreated()
            ->assertJsonPath('transaction.account.id', $this->account->id)
            ->assertJsonPath('transaction.fromAccount.id', $this->account->id)
            ->assertJsonPath('transaction.toAccount.id', $this->expenseAccount->id)
            ->assertJsonPath('transaction.note', 'Lunch')
            ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_DEBIT);

        $this->assertDatabaseHas('transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'category_id' => null,
            'note' => 'Lunch',
        ]);

            $this->assertArrayNotHasKey('category', $response->json('transaction'));
    }

    public function test_it_does_not_expose_the_legacy_transaction_form_options_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/transactions/form-options');

        $response->assertNotFound();
    }

    public function test_it_rejects_legacy_category_backed_transaction_payloads(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'category_type' => Category::EXPENSES,
            'amount' => 15,
            'created_at' => now()->toDateString(),
            'transaction_type' => Transaction::TYPE_DEBIT,
            'note' => 'Legacy request',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id', 'category_id', 'category_type', 'transaction_type']);
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
            ->assertJsonPath('transaction.note', 'Groceries');

        $this->assertArrayNotHasKey('category', $response->json('transaction'));
    }

    public function test_it_localizes_nested_account_names_for_the_active_locale(): void
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
            ->assertJsonPath('transaction.account.name_translations.en', 'Checking');

        $this->assertArrayNotHasKey('category', $response->json('transaction'));
    }

    public function test_it_localizes_audit_snapshots_for_the_active_locale(): void
    {
        $this->user->forceFill(['locale' => 'ar'])->save();

        $this->account->update([
            'name' => ['en' => 'Checking', 'ar' => 'الحساب الجاري'],
        ]);
        $this->expenseAccount->update([
            'name' => ['en' => 'Food Expense', 'ar' => 'مصروف الطعام'],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 42,
            'created_at' => now()->toDateString(),
            'note' => 'Lunch',
        ]);

        $response->assertCreated();

        $audit = TransactionAudit::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame('الحساب الجاري', $audit->new_values['account_name']);
        $this->assertSame('الحساب الجاري', $audit->new_values['from_account_name']);
        $this->assertSame('مصروف الطعام', $audit->new_values['to_account_name']);
        $this->assertArrayNotHasKey('category_id', $audit->new_values);
        $this->assertArrayNotHasKey('category_name', $audit->new_values);
    }

    public function test_it_updates_a_credit_transaction_and_its_account_reference(): void
    {
        $secondaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings'],
        ]);
        $transaction = Transaction::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->incomeAccount->id,
            'to_account_id' => $this->account->id,
            'amount' => 50,
            'transaction_type' => Transaction::TYPE_CREDIT,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $this->incomeAccount->id,
            'to_account_id' => $secondaryAccount->id,
            'amount' => 75,
            'created_at' => now()->toDateString(),
            'note' => 'Moved',
        ]);

        $response->assertOk()
            ->assertJsonPath('transaction.account.id', $secondaryAccount->id)
            ->assertJsonPath('transaction.fromAccount.id', $this->incomeAccount->id)
            ->assertJsonPath('transaction.toAccount.id', $secondaryAccount->id)
            ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_CREDIT)
            ->assertJsonPath('transaction.amount', 75)
            ->assertJsonPath('transaction.note', 'Moved');

        $this->assertArrayNotHasKey('category', $response->json('transaction'));
    }

    public function test_it_updates_an_account_to_account_transaction_without_a_category(): void
    {
        $updatedExpenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Transport Expense'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $createResponse = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 50,
            'created_at' => now()->toDateString(),
            'note' => 'Old transfer',
        ]);

        $transactionId = (int) $createResponse->json('transaction.id');

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transactionId}", [
            'from_account_id' => $this->incomeAccount->id,
            'to_account_id' => $updatedExpenseAccount->id,
            'amount' => 75,
            'created_at' => now()->toDateString(),
            'note' => 'Moved',
        ]);

        $response->assertOk()
            ->assertJsonPath('transaction.account.id', $updatedExpenseAccount->id)
            ->assertJsonPath('transaction.fromAccount.id', $this->incomeAccount->id)
            ->assertJsonPath('transaction.toAccount.id', $updatedExpenseAccount->id)
            ->assertJsonPath('transaction.amount', 75)
            ->assertJsonPath('transaction.transaction_type', Transaction::TYPE_CREDIT)
            ->assertJsonPath('transaction.note', 'Moved');

        $this->assertArrayNotHasKey('category', $response->json('transaction'));
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
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_EXPENSE,
        ]);

        $showResponse = $this->actingAs($this->user)->getJson("/api/v1/transactions/{$transaction->id}");

        $updateResponse = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $this->account->id,
            'to_account_id' => $destinationAccount->id,
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
            ->assertJsonPath('data.0.canEdit', false);

            $this->assertArrayNotHasKey('category', $indexResponse->json('data.0'));

        $showResponse->assertOk()
            ->assertJsonPath('transaction.id', $transaction->id)
            ->assertJsonPath('transaction.canEdit', false)
            ->assertJsonPath('transaction.account.ownerName', $owner->name)
            ->assertJsonPath('transaction.account.permissionLevel', Account::PERMISSION_VIEW);

            $this->assertArrayNotHasKey('category', $showResponse->json('transaction'));
    }

    public function test_view_shared_user_cannot_write_transactions(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($viewer->id, ['permission_level' => Account::PERMISSION_VIEW]);
        $viewerSourceAccount = Account::factory()->create([
            'user_id' => $viewer->id,
        ]);
        $viewerDestinationAccount = Account::factory()->create([
            'user_id' => $viewer->id,
            'type' => Account::TYPE_EXPENSE,
        ]);

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
            'from_account_id' => $account->id,
            'to_account_id' => $viewerDestinationAccount->id,
            'amount' => 20,
            'created_at' => now()->toDateString(),
        ]);

        $updateResponse = $this->actingAs($viewer)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $viewerSourceAccount->id,
            'to_account_id' => $viewerDestinationAccount->id,
            'amount' => 55,
            'created_at' => now()->toDateString(),
        ]);

        $deleteResponse = $this->actingAs($viewer)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['from_account_id']);
        $updateResponse->assertStatus(403);
        $deleteResponse->assertStatus(403);
    }

    public function test_edit_shared_user_can_write_transactions(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);
        $editorDestinationAccount = Account::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Food'],
            'type' => Account::TYPE_EXPENSE,
        ]);
        $updatedDestinationAccount = Account::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Transport'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $createResponse = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'from_account_id' => $account->id,
            'to_account_id' => $editorDestinationAccount->id,
            'amount' => 44,
            'created_at' => now()->toDateString(),
            'note' => 'Shared write',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('transaction.account.id', $account->id)
            ->assertJsonPath('transaction.canEdit', true);

        $transactionId = $createResponse->json('transaction.id');

        $updateResponse = $this->actingAs($editor)->putJson("/api/v1/transactions/{$transactionId}", [
            'from_account_id' => $account->id,
            'to_account_id' => $updatedDestinationAccount->id,
            'amount' => 66,
            'created_at' => now()->toDateString(),
            'note' => 'Updated shared write',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('transaction.toAccount.id', $updatedDestinationAccount->id)
            ->assertJsonPath('transaction.note', 'Updated shared write');

        $deleteResponse = $this->actingAs($editor)->deleteJson("/api/v1/transactions/{$transactionId}");

        $deleteResponse->assertOk();
    }

    public function test_edit_shared_user_cannot_create_transaction_with_an_inaccessible_destination_account(): void
    {
        $owner = $this->createUser();
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);
        $ownerDestinationAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Owner Food'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $response = $this->actingAs($editor)->postJson('/api/v1/transactions', [
            'from_account_id' => $account->id,
            'to_account_id' => $ownerDestinationAccount->id,
            'amount' => 22,
            'created_at' => now()->toDateString(),
            'note' => 'Editor destination on shared account',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    public function test_owner_cannot_create_a_new_transaction_with_a_shared_users_private_destination_account(): void
    {
        $owner = $this->createUser(['name' => 'Primary Owner']);
        $editor = $this->createUser();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        $account->sharedUsers()->attach($editor->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $editorDestinationAccount = Account::factory()->create([
            'user_id' => $editor->id,
            'name' => ['en' => 'Editor Expense'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $createResponse = $this->actingAs($owner)->postJson('/api/v1/transactions', [
            'from_account_id' => $account->id,
            'to_account_id' => $editorDestinationAccount->id,
            'amount' => 27,
            'created_at' => now()->toDateString(),
            'note' => 'Owner reused shared destination',
        ]);

        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['to_account_id']);
    }

    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }
}
