<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateTransferTool;
use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Tests\TestCase;

class CreateTransferToolTest extends TestCase
{
    use RefreshDatabase;

    private CreateTransferTool $tool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->tool = new CreateTransferTool();
    }

    public function test_it_creates_matching_debit_and_credit_transactions_for_a_transfer(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Cash Wallet', 'ar' => null],
            'balance' => 500,
            'currency' => 'EGP',
        ]);

        $sharedOwner = User::factory()->create();
        $destinationAccount = Account::factory()->create([
            'user_id' => $sharedOwner->id,
            'name' => ['en' => 'Family Vault', 'ar' => null],
            'balance' => 120,
            'currency' => 'EGP',
        ]);
        $destinationAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $result = $this->tool->handle(new Request([
            'amount' => 150,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
            'note' => 'Move to shared savings',
            'date' => '2026-04-14',
        ]));

        $transactions = Transaction::withoutGlobalScopes()
            ->with(['account', 'category'])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $transactions);
        $this->assertStringContainsString('Transfer created successfully', $result);
        $this->assertStringContainsString('Outgoing:', $result);
        $this->assertStringContainsString('Incoming:', $result);

        $outgoingTransaction = $transactions->firstWhere('account_id', $sourceAccount->id);
        $incomingTransaction = $transactions->firstWhere('account_id', $destinationAccount->id);

        $this->assertNotNull($outgoingTransaction);
        $this->assertNotNull($incomingTransaction);
        $this->assertSame(Transaction::TYPE_DEBIT, $outgoingTransaction->transaction_type);
        $this->assertSame(Transaction::TYPE_CREDIT, $incomingTransaction->transaction_type);
        $this->assertSame(Category::EXPENSES, $outgoingTransaction->category->type);
        $this->assertSame(Category::INCOME, $incomingTransaction->category->type);
        $this->assertSame('Move to shared savings | Transfer to: Family Vault', $outgoingTransaction->note);
        $this->assertSame('Move to shared savings | Transfer from: Cash Wallet', $incomingTransaction->note);
        $this->assertSame('2026-04-14', $outgoingTransaction->created_at->format('Y-m-d'));
        $this->assertSame('2026-04-14', $incomingTransaction->created_at->format('Y-m-d'));
        $this->assertSame(350.0, $sourceAccount->fresh()->balance);
        $this->assertSame(270.0, $destinationAccount->fresh()->balance);
    }

    public function test_it_requires_edit_access_to_both_accounts(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'EGP',
        ]);

        $owner = User::factory()->create();
        $destinationAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'currency' => 'EGP',
        ]);
        $destinationAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_VIEW]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to modify transactions for the specified account.');

        $this->tool->handle(new Request([
            'amount' => 50,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
        ]));
    }

    public function test_it_rejects_cross_currency_transfers(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
        ]);
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'currency' => 'EUR',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transfers between accounts with different currencies are not supported yet.');

        $this->tool->handle(new Request([
            'amount' => 50,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
        ]));
    }
}
