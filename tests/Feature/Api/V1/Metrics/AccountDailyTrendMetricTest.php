<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use Carbon\Carbon;

class AccountDailyTrendMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/account-daily-trend?id=1');
        $response->assertUnauthorized();
    }

    public function test_returns_daily_data(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $today = Carbon::now()->startOfDay();

        $this->createLedgerTransaction($checking, $food, 100, ['created_at' => $today]);

        $from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/account-daily-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
    }

    public function test_fills_missing_days_with_zero(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $date = Carbon::now()->startOfMonth()->addDays(5);

        $this->createLedgerTransaction($checking, $food, 100, ['created_at' => $date]);

        $from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/account-daily-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $items = $response->json('data.items');
        $daysInMonth = Carbon::now()->daysInMonth;
        $this->assertGreaterThanOrEqual($daysInMonth, count($items));
    }

    public function test_filters_by_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $savings = $this->createAccount(['name' => ['en' => 'Savings'], 'type' => Account::TYPE_ASSET]);
        $date = Carbon::now()->startOfMonth()->addDays(5);

        $this->createLedgerTransaction($checking, $food, 100, ['created_at' => $date]);
        $this->createLedgerTransaction($salary, $savings, 5000, ['created_at' => $date]);

        $from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/account-daily-trend?from={$from}&to={$to}&id={$checking->id}");

        $response->assertOk();
        $items = $response->json('data.items');
        $dayData = collect($items)->firstWhere('label', $date->format('Y-m-d'));
        $this->assertEquals(100, $dayData['value']);
    }
}