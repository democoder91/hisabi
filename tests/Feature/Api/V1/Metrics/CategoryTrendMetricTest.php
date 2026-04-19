<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Transaction\Models\Transaction;
use Carbon\Carbon;

class CategoryTrendMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/category-trend?id=1');
        $response->assertUnauthorized();
    }

    public function test_returns_data_for_category(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create([
            'category_id' => $this->expensesCategory->id,
            'amount' => 500,
            'created_at' => Carbon::now()->startOfMonth()
        ]);

        $response = $this->getJson('/api/v1/metrics/category-trend?range=current-year&id=' . $this->expensesCategory->id);

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_filters_by_category(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create([
            'category_id' => $this->expensesCategory->id,
            'amount' => 500,
            'created_at' => Carbon::now()->startOfMonth()
        ]);
        Transaction::factory()->create([
            'category_id' => $this->incomeCategory->id,
            'amount' => 5000,
            'created_at' => Carbon::now()->startOfMonth()
        ]);

        $response = $this->getJson('/api/v1/metrics/category-trend?range=current-year&id=' . $this->expensesCategory->id);

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/category-trend?range=current-year&id=' . $this->expensesCategory->id);

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
