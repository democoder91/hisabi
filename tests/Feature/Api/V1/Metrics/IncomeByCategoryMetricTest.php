<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;

class IncomeByCategoryMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/income-by-category');
        $response->assertUnauthorized();
    }

    public function test_returns_grouped_data(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);

        $response = $this->getJson('/api/v1/metrics/income-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals('Salary', $items[0]['label']);
        $this->assertEquals(5000, $items[0]['value']);
    }

    public function test_excludes_expenses(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);
        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);

        $response = $this->getJson('/api/v1/metrics/income-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('Salary', $items[0]['label']);
    }

    public function test_groups_multiple_categories(): void
    {
        $this->actingAs($this->user);

        $freelanceCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::INCOME,
            'name' => ['en' => 'Freelance'],
        ]);

        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);
        Transaction::factory()->create(['category_id' => $freelanceCategory->id, 'amount' => 2000]);

        $response = $this->getJson('/api/v1/metrics/income-by-category?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/income-by-category?range=current-year');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
