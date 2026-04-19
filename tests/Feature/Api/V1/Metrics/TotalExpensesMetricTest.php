<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class TotalExpensesMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-expenses');
        $response->assertUnauthorized();
    }

    public function test_returns_correct_value(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($checking, $food, 300);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/total-expenses?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(800, $response->json('data.value'));
    }

    public function test_excludes_income(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($salary, $checking, 5000);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/total-expenses?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(500, $response->json('data.value'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/total-expenses?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }
}
