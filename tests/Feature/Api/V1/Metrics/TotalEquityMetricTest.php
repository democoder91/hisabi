<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class TotalEquityMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-equity');
        $response->assertUnauthorized();
    }

    public function test_returns_net_equity_movement(): void
    {
        $this->actingAs($this->user);

        $capital = $this->createAccount(['name' => ['en' => 'Capital'], 'type' => Account::TYPE_EQUITY]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($capital, $checking, 3000);
        $this->createLedgerTransaction($checking, $capital, 500);

        $response = $this->getJson('/api/v1/metrics/total-equity');

        $response->assertOk();
        $this->assertEquals(2500, $response->json('data.value'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/total-equity');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }
}