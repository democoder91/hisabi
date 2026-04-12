<?php

namespace Tests\Feature\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use App\Domains\Transaction\Models\TransactionAudit;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuditControllerTest extends TestCase
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
            'name' => ['en' => 'Checking', 'ar' => 'الحساب الجاري'],
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

    public function test_creating_a_transaction_generates_an_audit_record(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/transactions', [
            'account_id' => $this->account->id,
            'amount' => 42,
            'brand_id' => $this->brand->id,
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
        $this->assertEquals(42.0, $audit->new_values['amount']);
        $this->assertSame('Lunch', $audit->new_values['note']);
        $this->assertSame('Cafe', $audit->new_values['brand_name']);
        $this->assertSame('Food', $audit->new_values['category_name']);
    }

    public function test_updating_a_transaction_captures_old_and_new_values(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 40,
            'note' => 'Before',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'amount' => 75,
            'brand_id' => $this->brand->id,
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
        $this->assertSame('Cafe', $audit->new_values['brand_name']);
    }

    public function test_deleting_a_transaction_generates_a_delete_audit_record(): void
    {
        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 18,
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
            'account_id' => $this->account->id,
            'amount' => 33,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
        ])->json('transaction.id');

        $otherOwner = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherOwner->id]);
        $otherCategory = Category::factory()->create([
            'user_id' => $otherOwner->id,
            'name' => ['en' => 'Transport'],
            'type' => Category::EXPENSES,
        ]);
        $otherBrand = Brand::factory()->create([
            'user_id' => $otherOwner->id,
            'name' => ['en' => 'Taxi'],
            'category_id' => $otherCategory->id,
        ]);

        $this->actingAs($otherOwner)->postJson('/api/v1/transactions', [
            'account_id' => $otherAccount->id,
            'amount' => 19,
            'brand_id' => $otherBrand->id,
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
            'account_id' => $this->account->id,
            'amount' => 11,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($sharedUser)->getJson("/api/v1/accounts/{$this->account->id}/audits");

        $response->assertStatus(403);
    }

    public function test_moving_a_transaction_to_another_account_records_update_audits_for_both_accounts(): void
    {
        $secondaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Savings', 'ar' => 'المدخرات'],
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'brand_id' => $this->brand->id,
            'amount' => 28,
            'note' => 'Before move',
        ]);

        $this->actingAs($this->user)->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $secondaryAccount->id,
            'amount' => 64,
            'brand_id' => $this->brand->id,
            'created_at' => now()->toDateString(),
            'note' => 'Moved accounts',
        ])->assertOk();

        $audits = TransactionAudit::query()
            ->where('transaction_id', $transaction->id)
            ->where('action', TransactionAudit::ACTION_UPDATED)
            ->orderBy('account_id')
            ->get();

        $this->assertCount(2, $audits);
        $this->assertSame([
            $this->account->id,
            $secondaryAccount->id,
        ], $audits->pluck('account_id')->all());
        $this->assertSame($this->account->id, $audits[0]->old_values['account_id']);
        $this->assertSame($secondaryAccount->id, $audits[0]->new_values['account_id']);
        $this->assertSame($this->account->id, $audits[1]->old_values['account_id']);
        $this->assertSame($secondaryAccount->id, $audits[1]->new_values['account_id']);
    }
}