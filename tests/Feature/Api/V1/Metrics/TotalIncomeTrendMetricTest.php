<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use Carbon\Carbon;

class TotalIncomeTrendMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-income-trend');
        $response->assertUnauthorized();
    }

    public function test_returns_monthly_data(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000, ['created_at' => Carbon::now()->startOfMonth()]);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income-trend?from={$from}&to={$to}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals(5000, $items[0]['value']);
    }

    public function test_groups_by_month(): void
    {
        $this->actingAs($this->user);

        $thisMonth = Carbon::now()->startOfMonth()->addDays(5);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 3000, ['created_at' => $thisMonth]);
        $this->createLedgerTransaction($salary, $checking, 2000, ['created_at' => $thisMonth->copy()->addDays(5)]);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income-trend?from={$from}&to={$to}");

        $response->assertOk();
        $items = $response->json('data.items');

        $this->assertCount(1, $items);
        $this->assertEquals(5000, $items[0]['value']);
    }

    public function test_excludes_expenses(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 5000, ['created_at' => Carbon::now()->startOfMonth()]);
        $this->createLedgerTransaction($checking, $food, 1000, ['created_at' => Carbon::now()->startOfMonth()]);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income-trend?from={$from}&to={$to}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertEquals(5000, $items[0]['value']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income-trend?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
