<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use Carbon\Carbon;

class AccountTrendMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/account-trend?id=1');
        $response->assertUnauthorized();
    }

    public function test_returns_data_for_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $date = Carbon::now()->startOfMonth();

        $this->createLedgerTransaction($checking, $food, 500, ['created_at' => $date]);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');
        $response = $this->getJson("/api/v1/metrics/account-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_filters_by_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $savings = $this->createAccount(['name' => ['en' => 'Savings'], 'type' => Account::TYPE_ASSET]);
        $date = Carbon::now()->startOfMonth();

        $this->createLedgerTransaction($checking, $food, 500, ['created_at' => $date]);
        $this->createLedgerTransaction($salary, $savings, 5000, ['created_at' => $date]);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');
        $response = $this->getJson("/api/v1/metrics/account-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/account-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}