<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use Carbon\Carbon;

class TotalIncomeMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-income');
        $response->assertUnauthorized();
    }

    public function test_returns_correct_value(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($salary, $checking, 3000);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(8000, $response->json('data.value'));
    }

    public function test_excludes_expenses(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($checking, $food, 1000);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(5000, $response->json('data.value'));
    }

    public function test_returns_previous_period(): void
    {
        $this->actingAs($this->user);

        $currentMonthDate = Carbon::now()->startOfMonth()->addDays(5);
        $lastMonthDate = Carbon::now()->subMonth()->startOfMonth()->addDays(5);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000, ['created_at' => $currentMonthDate]);
        $this->createLedgerTransaction($salary, $checking, 4000, ['created_at' => $lastMonthDate]);

        $from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(5000, $response->json('data.value'));
        $this->assertEquals(4000, $response->json('data.previous'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }

    public function test_filters_by_current_month(): void
    {
        $this->actingAs($this->user);

        $currentMonthDate = Carbon::now()->startOfMonth()->addDays(5);
        $twoMonthsAgoDate = Carbon::now()->subMonths(2)->startOfMonth()->addDays(5);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000, ['created_at' => $currentMonthDate]);
        $this->createLedgerTransaction($salary, $checking, 3000, ['created_at' => $twoMonthsAgoDate]);

        $from = Carbon::now()->startOfMonth()->format('Y-m-d');
        $to = Carbon::now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/metrics/total-income?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(5000, $response->json('data.value'));
    }
}
