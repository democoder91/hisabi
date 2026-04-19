<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class NetWorthMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/net-worth');
        $response->assertUnauthorized();
    }

    public function test_returns_correct_value(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 10000);
        $this->createLedgerTransaction($checking, $food, 3000);

        $response = $this->getJson('/api/v1/metrics/net-worth');

        $response->assertOk();
        $this->assertEquals(7000, $response->json('data.value'));
    }

    public function test_returns_negative_when_liabilities_outpace_assets(): void
    {
        $this->actingAs($this->user);

        $loan = $this->createAccount(['name' => ['en' => 'Loan'], 'type' => Account::TYPE_LIABILITY]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($loan, $food, 3000);

        $response = $this->getJson('/api/v1/metrics/net-worth');

        $response->assertOk();
        $this->assertEquals(-3000, $response->json('data.value'));
    }

    public function test_ignores_equity_when_computing_net_worth(): void
    {
        $this->actingAs($this->user);

        $capital = $this->createAccount(['name' => ['en' => 'Capital'], 'type' => Account::TYPE_EQUITY]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($capital, $checking, 5000);

        $response = $this->getJson('/api/v1/metrics/net-worth');

        $response->assertOk();
        $this->assertEquals(5000, $response->json('data.value'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/net-worth');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }
}
