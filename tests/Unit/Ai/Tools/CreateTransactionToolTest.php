<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\CreateTransactionTool;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CreateTransactionToolTest extends TestCase
{
    use RefreshDatabase;

    private CreateTransactionTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new CreateTransactionTool();
    }

    public function test_it_creates_transaction_with_all_parameters(): void
    {
        $category = Category::factory()->create(['type' => Category::EXPENSES, 'name' => 'Food']);
        $brand = Brand::factory()->create(['name' => 'Starbucks', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 25.50,
            'brand_name' => 'Starbucks',
            'category_type' => 'EXPENSES',
            'currency' => 'USD',
            'note' => 'Morning coffee',
            'date' => '2026-04-11',
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('USD', $result);
        $this->assertStringContains('25.5', $result);
        $this->assertStringContains('Starbucks', $result);

        $transaction = Transaction::latest()->first();
        $this->assertEquals(25.50, $transaction->amount);
        $this->assertEquals('USD', $transaction->currency);
        $this->assertEquals('Morning coffee', $transaction->note);
        $this->assertEquals($brand->id, $transaction->brand_id);
    }

    public function test_it_creates_new_brand_if_not_exists(): void
    {
        $request = new Request([
            'amount' => 100,
            'brand_name' => 'NewMerchant',
            'category_type' => 'EXPENSES',
            'currency' => 'AED',
        ]);

        $this->tool->handle($request);

        $brand = Brand::where('name', 'NewMerchant')->first();
        $this->assertNotNull($brand);
        $this->assertNotNull($brand->category_id);

        $transaction = Transaction::latest()->first();
        $this->assertEquals(100, $transaction->amount);
        $this->assertEquals($brand->id, $transaction->brand_id);
    }

    public function test_it_reuses_existing_brand(): void
    {
        $category = Category::factory()->create(['type' => Category::EXPENSES]);
        Brand::factory()->create(['name' => 'McDonald', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 30,
            'brand_name' => 'McDonald',
            'category_type' => 'EXPENSES',
            'currency' => 'AED',
        ]);

        $this->tool->handle($request);

        $this->assertEquals(1, Brand::where('name', 'McDonald')->count());
        $transaction = Transaction::latest()->first();
        $this->assertEquals(30, $transaction->amount);
    }

    public function test_it_assigns_category_to_uncategorized_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'UnknownShop', 'category_id' => null]);

        $request = new Request([
            'amount' => 50,
            'brand_name' => 'UnknownShop',
            'category_type' => 'INCOME',
            'currency' => 'AED',
        ]);

        $this->tool->handle($request);

        $brand->refresh();
        $this->assertNotNull($brand->category_id);
        $this->assertEquals(Category::INCOME, $brand->category->type);
    }

    public function test_it_uses_user_default_currency_when_not_specified(): void
    {
        $user = User::factory()->create(['default_currency' => 'EUR']);
        $this->actingAs($user);

        $category = Category::factory()->create(['type' => Category::EXPENSES]);
        Brand::factory()->create(['name' => 'TestBrand', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 75,
            'brand_name' => 'TestBrand',
            'category_type' => 'EXPENSES',
        ]);

        $result = $this->tool->handle($request);

        $transaction = Transaction::latest()->first();
        $this->assertEquals('EUR', $transaction->currency);
        $this->assertStringContains('EUR', $result);
    }

    public function test_it_uses_system_default_currency_when_no_user_preference(): void
    {
        $user = User::factory()->create(['default_currency' => null]);
        $this->actingAs($user);

        $category = Category::factory()->create(['type' => Category::SAVINGS]);
        Brand::factory()->create(['name' => 'Bank', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 500,
            'brand_name' => 'Bank',
            'category_type' => 'SAVINGS',
        ]);

        $this->tool->handle($request);

        $transaction = Transaction::latest()->first();
        $this->assertEquals(config('hisabi.currency'), $transaction->currency);
    }

    public function test_it_defaults_date_to_today(): void
    {
        $category = Category::factory()->create(['type' => Category::EXPENSES]);
        Brand::factory()->create(['name' => 'Shop', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 10,
            'brand_name' => 'Shop',
            'category_type' => 'EXPENSES',
            'currency' => 'AED',
        ]);

        $this->tool->handle($request);

        $transaction = Transaction::latest()->first();
        $this->assertEquals(now()->toDateString(), $transaction->created_at->toDateString());
    }

    public function test_it_returns_confirmation_with_note(): void
    {
        $category = Category::factory()->create(['type' => Category::INVESTMENT]);
        Brand::factory()->create(['name' => 'Broker', 'category_id' => $category->id]);

        $request = new Request([
            'amount' => 1000,
            'brand_name' => 'Broker',
            'category_type' => 'INVESTMENT',
            'currency' => 'USD',
            'note' => 'Monthly investment',
            'date' => '2026-04-01',
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('Monthly investment', $result);
        $this->assertStringContains('USD', $result);
        $this->assertStringContains('1000', $result);
        $this->assertStringContains('Broker', $result);
    }

    public function test_it_creates_income_transaction(): void
    {
        $request = new Request([
            'amount' => 5000,
            'brand_name' => 'Employer',
            'category_type' => 'INCOME',
            'currency' => 'AED',
            'note' => 'Salary',
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContains('Transaction created successfully', $result);
        $transaction = Transaction::latest()->first();
        $this->assertEquals(5000, $transaction->amount);
        $this->assertEquals('AED', $transaction->currency);
        $this->assertEquals('Salary', $transaction->note);
    }

    public function test_it_creates_transaction_without_brand(): void
    {
        $request = new Request([
            'amount' => 42,
            'category_type' => 'EXPENSES',
            'currency' => 'AED',
            'note' => 'Quick purchase',
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContains('Transaction created successfully', $result);
        $this->assertStringContains('42', $result);
        $this->assertStringNotContains(' at ', $result);

        $transaction = Transaction::latest()->first();
        $this->assertEquals(42, $transaction->amount);
        $this->assertNull($transaction->brand_id);
        $this->assertEquals('Quick purchase', $transaction->note);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertStringNotContainsString($needle, $haystack);
    }
}
