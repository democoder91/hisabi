<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;

class ExpensesByCategoryMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/expenses-by-category');
        $response->assertUnauthorized();
    }

    public function test_returns_grouped_data(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);

        $response = $this->getJson('/api/v1/metrics/expenses-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals('Food', $items[0]['label']);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_excludes_income(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);
        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);

        $response = $this->getJson('/api/v1/metrics/expenses-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('Food', $items[0]['label']);
    }

    public function test_groups_multiple_categories(): void
    {
        $this->actingAs($this->user);

        $transportCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Transport'],
        ]);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);
        Transaction::factory()->create(['category_id' => $transportCategory->id, 'amount' => 300]);

        $response = $this->getJson('/api/v1/metrics/expenses-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
    }

    public function test_orders_by_value_descending(): void
    {
        $this->actingAs($this->user);

        $transportCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Transport'],
        ]);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 300]);
        Transaction::factory()->create(['category_id' => $transportCategory->id, 'amount' => 500]);

        $response = $this->getJson('/api/v1/metrics/expenses-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertEquals('Transport', $items[0]['label']);
        $this->assertEquals('Food', $items[1]['label']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/expenses-by-category?range=current-year');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
