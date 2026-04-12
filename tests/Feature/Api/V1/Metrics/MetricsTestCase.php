<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Brand\Models\Brand;
use App\Domains\Category\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MetricsTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $incomeCategory;
    protected Category $expensesCategory;
    protected Category $savingsCategory;
    protected Category $investmentCategory;
    protected Brand $incomeBrand;
    protected Brand $expensesBrand;
    protected Brand $savingsBrand;
    protected Brand $investmentBrand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->incomeCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::INCOME,
            'name' => ['en' => 'Salary'],
        ]);
        $this->expensesCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Food'],
        ]);
        $this->savingsCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::SAVINGS,
            'name' => ['en' => 'Emergency Fund'],
        ]);
        $this->investmentCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::INVESTMENT,
            'name' => ['en' => 'Stocks'],
        ]);

        $this->incomeBrand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->incomeCategory->id,
            'name' => ['en' => 'Company A'],
        ]);
        $this->expensesBrand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->expensesCategory->id,
            'name' => ['en' => 'Restaurant'],
        ]);
        $this->savingsBrand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->savingsCategory->id,
            'name' => ['en' => 'Bank'],
        ]);
        $this->investmentBrand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->investmentCategory->id,
            'name' => ['en' => 'Broker'],
        ]);
    }
}
