<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateTransactionTool;
use App\Domains\Account\Models\Account;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Tests\TestCase;

class CreateTransactionToolTest extends TestCase
{
    use RefreshDatabase;

    private CreateTransactionTool $tool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['default_currency' => 'EUR']);
        $this->actingAs($this->user);
        $this->tool = new CreateTransactionTool();
    }

    public function test_it_uses_the_source_account_currency_even_when_the_ai_sends_a_different_currency(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Wallet', 'ar' => null],
            'balance' => 200,
            'currency' => 'USD',
            'type' => Account::TYPE_ASSET,
        ]);
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_EXPENSE,
            'name' => ['en' => 'Food Expense', 'ar' => null],
            'currency' => 'EUR',
        ]);

        $result = $this->tool->handle(new Request([
            'amount' => 25.50,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
            'brand_name' => 'Starbucks',
            'currency' => 'EUR',
            'note' => 'Morning coffee',
            'date' => '2026-04-11',
        ]));

        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('USD', $result);
        $this->assertStringContains('25.50', $result);
        $this->assertStringContains('Wallet', $result);
        $this->assertStringContains('Food Expense', $result);

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals($sourceAccount->id, $transaction->account_id);
        $this->assertEquals($sourceAccount->id, $transaction->from_account_id);
        $this->assertEquals($destinationAccount->id, $transaction->to_account_id);
        $this->assertNull($transaction->category_id);
        $this->assertEquals(25.50, $transaction->amount);
        $this->assertEquals('USD', $transaction->currency);
        $this->assertEquals('Morning coffee | Merchant: Starbucks', $transaction->note);
        $this->assertEquals(Transaction::TYPE_DEBIT, $transaction->transaction_type);
        $this->assertEquals('2026-04-11', $transaction->created_at->format('Y-m-d'));
    }

    public function test_it_uses_the_source_account_currency_when_the_ai_omits_currency(): void
    {
        $incomeAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_INCOME,
            'currency' => 'USD',
            'name' => ['en' => 'Salary', 'ar' => null],
        ]);
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_ASSET,
            'currency' => 'USD',
            'name' => ['en' => 'Main Wallet', 'ar' => null],
        ]);

        $result = $this->tool->handle(new Request([
            'amount' => 100,
            'from_account_id' => $incomeAccount->id,
            'to_account_id' => $destinationAccount->id,
        ]));

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->with(['account', 'fromAccount', 'toAccount'])->first();

        $this->assertEquals($destinationAccount->id, $transaction->account_id);
        $this->assertEquals($incomeAccount->id, $transaction->from_account_id);
        $this->assertEquals($destinationAccount->id, $transaction->to_account_id);
        $this->assertEquals(Transaction::TYPE_CREDIT, $transaction->transaction_type);
        $this->assertEquals('USD', $transaction->currency);
        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('USD 100.00', $result);
    }

    public function test_it_normalizes_structured_ai_text_fields_before_validation(): void
    {
        $sourceAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Wallet', 'ar' => null],
            'type' => Account::TYPE_ASSET,
        ]);
        $destinationAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Food Expense', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $result = $this->tool->handle(new Request([
            'amount' => 150,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $destinationAccount->id,
            'brand_name' => ['name' => 'Fruit Market'],
            'note' => ['description' => 'Sweets and fruits'],
        ]));

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->first();

        $this->assertEquals('Sweets and fruits | Merchant: Fruit Market', $transaction->note);
        $this->assertStringContains('Sweets and fruits', $result);
        $this->assertStringContains('Fruit Market', $result);
    }

    public function test_it_requires_source_and_destination_accounts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The from account id field is required.');

        $this->tool->handle(new Request([
            'amount' => 40,
        ]));
    }

    public function test_it_rejects_using_the_same_account_as_source_and_destination(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => Account::TYPE_ASSET,
            'name' => ['en' => 'Wallet', 'ar' => null],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The from account id and to account id must be different.');

        $this->tool->handle(new Request([
            'amount' => 12,
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
        ]));
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
