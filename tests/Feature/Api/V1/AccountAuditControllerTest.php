<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Models\TransactionAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Checking', 'ar' => 'الحساب الجاري'],
        ]);
        $this->expenseAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food Expense', 'ar' => 'مصروف الطعام'],
            'type' => Account::TYPE_EXPENSE,
        ]);
    }

    public function test_creating_a_transaction_generates_an_audit_record(): void
    {
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
        $this->assertSame(TransactionAudit::ACTION_CREATED, $audit->action);
        $this->assertSame($this->account->id, $audit->account_id);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertSame('Checking', $audit->new_values['account_name']);
        $this->assertSame('Checking', $audit->new_values['from_account_name']);
        $this->assertSame('Food Expense', $audit->new_values['to_account_name']);
        $this->assertEquals(42.0, $audit->new_values['amount']);
        $this->assertSame('Lunch', $audit->new_values['note']);
        $this->assertArrayNotHasKey('category_id', $audit->new_values);
        $this->assertArrayNotHasKey('category_name', $audit->new_values);
    }

    public function test_updating_a_transaction_captures_old_and_new_values(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 40,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => $this->account->currency,
            'note' => 'Before',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 75,
            'created_at' => now()->toDateString(),
            'note' => 'After',
        ]);

        $response->assertOk();

        $audit = TransactionAudit::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame(TransactionAudit::ACTION_UPDATED, $audit->action);
        $this->assertEquals(40.0, $audit->old_values['amount']);
        $this->assertSame('Before', $audit->old_values['note']);
        $this->assertEquals(75.0, $audit->new_values['amount']);
        $this->assertSame('After', $audit->new_values['note']);
        $this->assertArrayNotHasKey('category_id', $audit->new_values);
        $this->assertArrayNotHasKey('category_name', $audit->new_values);
    }

    public function test_deleting_a_transaction_generates_a_delete_audit_record(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 18,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => $this->account->currency,
            'note' => 'To delete',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertOk();

        $audit = TransactionAudit::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame(TransactionAudit::ACTION_DELETED, $audit->action);
        $this->assertNull($audit->new_values);
        $this->assertEquals(18.0, $audit->old_values['amount']);
        $this->assertSame('To delete', $audit->old_values['note']);
        $this->assertSame($this->account->id, $audit->account_id);
    }

    public function test_owner_can_fetch_audits_for_their_account_only(): void
    {
        $ownedTransaction = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 33,
            'created_at' => now()->toDateString(),
        ])->json('transaction.id');

        $otherOwner = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherOwner->id]);
        $otherExpenseAccount = Account::factory()->create([
            'user_id' => $otherOwner->id,
            'name' => ['en' => 'Transport Expense'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $this->actingAs($otherOwner)->postJson('/api/v1/transactions', [
            'from_account_id' => $otherAccount->id,
            'to_account_id' => $otherExpenseAccount->id,
            'amount' => 19,
            'created_at' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$this->account->id}/audits");

        $response->assertOk()
            ->assertJsonPath('account.id', $this->account->id)
            ->assertJsonPath('paginatorInfo.total', 1)
            ->assertJsonPath('data.0.transactionId', $ownedTransaction);

        $forbiddenResponse = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$otherAccount->id}/audits");

        $forbiddenResponse->assertStatus(403);
    }

    public function test_shared_user_cannot_fetch_an_owners_audit_trail(): void
    {
        $sharedUser = User::factory()->create();
        $this->account->sharedUsers()->attach($sharedUser->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 11,
            'created_at' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($sharedUser)->getJson("/api/v1/accounts/{$this->account->id}/audits");

        $response->assertStatus(403);
    }

    public function test_moving_a_transaction_to_another_account_records_update_audits_for_all_affected_accounts(): void
    {
        $secondaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => 'المدخرات'],
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 28,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => $this->account->currency,
            'note' => 'Before move',
        ]);

        $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'from_account_id' => $secondaryAccount->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 64,
            'created_at' => now()->toDateString(),
            'note' => 'Moved accounts',
        ])->assertOk();

        $audits = TransactionAudit::query()
            ->where('transaction_id', $transaction->id)
            ->where('action', TransactionAudit::ACTION_UPDATED)
            ->orderBy('account_id')
            ->get();

        $this->assertCount(3, $audits);
        $this->assertSame(
            collect([$this->account->id, $secondaryAccount->id, $this->expenseAccount->id])->sort()->values()->all(),
            $audits->pluck('account_id')->sort()->values()->all()
        );
    }

    public function test_audit_api_response_hides_legacy_category_fields(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'category_id' => null,
            'from_account_id' => $this->account->id,
            'to_account_id' => $this->expenseAccount->id,
            'amount' => 50,
            'transaction_type' => Transaction::TYPE_DEBIT,
            'currency' => $this->account->currency,
        ]);

        TransactionAudit::query()->create([
            'transaction_id' => $transaction->id,
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => TransactionAudit::ACTION_UPDATED,
            'old_values' => [
                'account_id' => $this->account->id,
                'account_name' => 'Checking',
                'category_id' => 10,
                'category_name' => 'Food',
                'amount' => 10,
            ],
            'new_values' => [
                'account_id' => $this->account->id,
                'account_name' => 'Checking',
                'category_id' => 11,
                'category_name' => 'Dining',
                'amount' => 50,
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/accounts/{$this->account->id}/audits");

        $response->assertOk();

        $payload = $response->json('data.0');

        $this->assertArrayNotHasKey('category_id', $payload['oldValues']);
        $this->assertArrayNotHasKey('category_name', $payload['oldValues']);
        $this->assertArrayNotHasKey('category_id', $payload['newValues']);
        $this->assertArrayNotHasKey('category_name', $payload['newValues']);
        $this->assertNotContains('category_id', $payload['changedFields']);
        $this->assertNotContains('category_name', $payload['changedFields']);
    }
}
