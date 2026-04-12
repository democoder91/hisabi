<?php

namespace Tests\Unit\Models\Budgets;

use Tests\TestCase;
use App\Models\User;
use App\Domains\Brand\Models\Brand;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_name()
    {
        $sut = Budget::factory()->create(['name' => 'test']);

        $this->assertEquals("test", $sut->name);
    }

    public function test_it_belongs_to_categories()
    {
        $categories = Category::factory()->createMany(3);
        $sut = Budget::factory()->create();
        $sut->categories()->attach($categories);

        $this->assertCount(3, $sut->categories);
    }

    public function test_it_has_total_transactions_amount()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $brand = Brand::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $sut = Budget::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subDays(1), 
            'end_at' => now()->addDays(1), 
            'amount' => 700,
            'reoccurrence' => Budget::CUSTOM
        ]);
        $sut->categories()->attach($category);

        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 100]);
        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 200]);
        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 200, 'created_at' => now()->addDays(2)]);

        $this->assertEquals(300, $sut->totalTransactionsAmount);
        $this->assertEquals(42, $sut->totalSpentPercentage);
    }

    public function test_it_has_isSaving()
    {
        $this->assertTrue(Budget::factory()->create(['saving' => true])->isSaving);
        $this->assertFalse(Budget::factory()->create(['saving' => false])->isSaving);
    }

    public function test_it_has_totalMarginPerDay_should_return_zero_when_budget_ended()
    {
        // Freeze time to control the calculation precisely  
        $fixedNow = now()->setTime(12, 0, 0); // Noon today
        \Carbon\Carbon::setTestNow($fixedNow);
        
        // Create budget that ended this morning (past end date, so days < 0)
        $sut = Budget::factory()->create([
            'start_at' => $fixedNow->copy()->subDays(3), 
            'end_at' => $fixedNow->copy()->subHours(2), // ended 2 hours ago
            'amount' => 700, 
            'reoccurrence' => Budget::CUSTOM
        ]);
        $sut->categories()->attach(Category::factory()->create());

        // When budget has ended (days < 0), should return 0
        $this->assertEquals(0, $sut->totalMarginPerDay);
        
        \Carbon\Carbon::setTestNow(); // Reset time
    }

    public function test_it_has_totalMarginPerDay_should_return_zero_if_over_budget()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $brand = Brand::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $sut = Budget::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subDays(1), 
            'end_at' => now()->addDays(1), 
            'amount' => 700, 
            'reoccurrence' => Budget::CUSTOM
        ]);
        $sut->categories()->attach($category);

        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 700]);

        // Should return 0 when over budget (method returns 0, not string)
        $this->assertEquals(0, $sut->totalMarginPerDay);
    }

    public function test_it_has_totalMarginPerDay_should_return_correct_value()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $brand = Brand::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        // Create budget that ends exactly 2 full days from now to get predictable division
        $sut = Budget::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subDay(), 
            'end_at' => now()->addDays(2)->startOfDay(),
            'amount' => 700,
            'reoccurrence' => Budget::CUSTOM
        ]);
        $sut->categories()->attach($category);

        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 600]);

        // Calculate expected value: remaining amount divided by actual days remaining
        $remainingAmount = 700 - 600; // 100
        $daysRemaining = now()->diffInDays($sut->endAtDate);
        $expectedMargin = number_format($remainingAmount / $daysRemaining, 2);
        
        $this->assertEquals($expectedMargin, $sut->totalMarginPerDay);
    }

    public function test_it_has_start_and_end_at()
    {
        $sut = Budget::factory()->create(['start_at' => now()->subDays(1), 'end_at' => now()->addDays(1)]);

        $this->assertEquals(now()->subDays(1)->format('Y-m-d'), $sut->start_at->format('Y-m-d'));
        $this->assertEquals(now()->addDays(1)->format('Y-m-d'), $sut->end_at->format('Y-m-d'));
    }

    public function test_it_has_remaining_days()
    {
        // Freeze time to control the calculation precisely
        $fixedNow = now()->setTime(12, 0, 0); // Noon  
        \Carbon\Carbon::setTestNow($fixedNow);
        
        // Create budget ending tomorrow at start of day  
        $endDate = $fixedNow->copy()->addDay()->startOfDay();
        $sut = Budget::factory()->create([
            'start_at' => $fixedNow->copy()->subDay()->startOfDay(), 
            'end_at' => $endDate, 
            'reoccurrence' => Budget::CUSTOM
        ]);

        // Should be exactly 0.5 days from noon today to start of tomorrow
        $this->assertEquals(0.5, $sut->remainingDays);
        
        \Carbon\Carbon::setTestNow(); // Reset time
    }

    public function test_it_has_remaining_to_spend()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $brand = Brand::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $sut = Budget::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subDays(1), 
            'end_at' => now()->addDays(1), 
            'amount' => 700,
            'reoccurrence' => Budget::CUSTOM
        ]);
        $sut->categories()->attach($category);

        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 100]);
        Transaction::factory()->create(['brand_id' => $brand->id, 'amount' => 200]);

        $this->assertEquals('400', $sut->remainingToSpend);
    }

    public function test_it_has_start_and_end_dates_window()
    {
        $sut = Budget::factory()->create(['start_at' => now()->subDays(1), 'period' => 1, 'reoccurrence' => Budget::DAILY]);

        $this->assertEquals(now()->format('Y-m-d'), $sut->startAtDate);
        $this->assertEquals(now()->addDays(1)->format('Y-m-d'), $sut->endAtDate);
    }
}
