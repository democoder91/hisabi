<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateTransactionTool;
use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
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

    public function test_it_creates_transaction_for_a_specific_account_and_category(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Wallet', 'ar' => null],
            'balance' => 200,
            'currency' => 'USD',
        ]);
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Food', 'ar' => null],
        ]);

        $result = $this->tool->handle(new Request([
            'amount' => 25.50,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'brand_name' => 'Starbucks',
            'currency' => 'USD',
            'note' => 'Morning coffee',
            'date' => '2026-04-11',
        ]));

        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('USD', $result);
        $this->assertStringContains('25.50', $result);
        $this->assertStringContains('Wallet', $result);
        $this->assertStringContains('Food', $result);

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals($account->id, $transaction->account_id);
        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals(25.50, $transaction->amount);
        $this->assertEquals('USD', $transaction->currency);
        $this->assertEquals('Morning coffee | Merchant: Starbucks', $transaction->note);
        $this->assertEquals(Transaction::TYPE_DEBIT, $transaction->transaction_type);
        $this->assertEquals('2026-04-11', $transaction->created_at->format('Y-m-d'));
    }

    public function test_it_uses_default_account_and_fallback_category_when_only_category_type_is_provided(): void
    {
        $result = $this->tool->handle(new Request([
            'amount' => 100,
            'category_type' => 'expenses',
        ]));

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->with(['account', 'category'])->first();
        $defaultAccount = $this->user->getOrCreateDefaultAccount();

        $this->assertEquals($defaultAccount->id, $transaction->account_id);
        $this->assertEquals(Category::EXPENSES, $transaction->category->type);
        $this->assertEquals('EUR', $transaction->currency);
        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('EUR 100.00', $result);
    }

    public function test_it_normalizes_structured_ai_text_fields_before_validation(): void
    {
        $result = $this->tool->handle(new Request([
            'amount' => 150,
            'category_type' => 'expenses',
            'brand_name' => ['name' => 'Fruit Market'],
            'note' => ['description' => 'Sweets and fruits'],
        ]));

        $transaction = Transaction::withoutGlobalScopes()->latest('id')->first();

        $this->assertEquals('Sweets and fruits | Merchant: Fruit Market', $transaction->note);
        $this->assertStringContains('Sweets and fruits', $result);
        $this->assertStringContains('Fruit Market', $result);
    }

    public function test_it_requires_category_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provide either category_id or category_type to create a transaction.');

        $this->tool->handle(new Request([
            'amount' => 40,
        ]));
    }

    public function test_it_rejects_a_shared_users_own_category_for_an_account_owned_by_someone_else(): void
    {
        $owner = User::factory()->create();
        $sharedAccount = Account::factory()->create([
            'user_id' => $owner->id,
            'name' => ['en' => 'Joint Wallet', 'ar' => null],
        ]);
        $sharedAccount->sharedUsers()->attach($this->user->id, ['permission_level' => Account::PERMISSION_EDIT]);

        $viewerCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Viewer Food', 'ar' => null],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The selected category is invalid for the chosen account.');

        $this->tool->handle(new Request([
            'amount' => 12,
            'account_id' => $sharedAccount->id,
            'category_id' => $viewerCategory->id,
        ]));
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
