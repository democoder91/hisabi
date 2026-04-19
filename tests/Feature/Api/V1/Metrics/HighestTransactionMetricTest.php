<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Transaction\Models\Transaction;

class HighestTransactionMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/highest-transaction');
        $response->assertUnauthorized();
    }

    public function test_returns_highest_transaction_by_category(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 100]);
        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);
        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 300]);

        $response = $this->getJson('/api/v1/metrics/highest-transaction?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $foodCategory = collect($items)->firstWhere('label', 'Food');
        $this->assertEquals(500, $foodCategory['value']);
    }

    public function test_groups_by_category(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 500]);
        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);

        $response = $this->getJson('/api/v1/metrics/highest-transaction?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/highest-transaction?range=current-year');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
