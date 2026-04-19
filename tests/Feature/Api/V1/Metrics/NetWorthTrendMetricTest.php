<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Transaction\Models\Transaction;
use Carbon\Carbon;

class NetWorthTrendMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/net-worth-trend');
        $response->assertUnauthorized();
    }

    public function test_returns_monthly_data(): void
    {
        $this->actingAs($this->user);

        Transaction::factory()->create([
            'category_id' => $this->incomeCategory->id,
            'amount' => 5000,
            'created_at' => Carbon::now()->startOfMonth()
        ]);
        Transaction::factory()->create([
            'category_id' => $this->expensesCategory->id,
            'amount' => 2000,
            'created_at' => Carbon::now()->startOfMonth()
        ]);

        $response = $this->getJson('/api/v1/metrics/net-worth-trend?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_calculates_running_net_worth(): void
    {
        $this->actingAs($this->user);

        $lastMonth = Carbon::now()->subMonth()->startOfMonth()->addDays(5);
        $thisMonth = Carbon::now()->startOfMonth()->addDays(5);

        $t1 = Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);
        $t1->created_at = $lastMonth;
        $t1->save();

        $t2 = Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 3000]);
        $t2->created_at = $thisMonth;
        $t2->save();

        $response = $this->getJson('/api/v1/metrics/net-worth-trend?range=current-year');

        $response->assertOk();
        $items = $response->json('data.items');

        // Should show cumulative net worth
        $this->assertCount(2, $items);
        $this->assertEquals(5000, $items[0]['value']);
        $this->assertEquals(8000, $items[1]['value']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/net-worth-trend?range=current-year');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
