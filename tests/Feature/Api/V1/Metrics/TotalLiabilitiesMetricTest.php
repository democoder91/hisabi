<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class TotalLiabilitiesMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-liabilities');
        $response->assertUnauthorized();
    }

    public function test_returns_net_liability_movement(): void
    {
        $this->actingAs($this->user);

        $loan = $this->createAccount(['name' => ['en' => 'Loan'], 'type' => Account::TYPE_LIABILITY]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($loan, $checking, 2000);
        $this->createLedgerTransaction($checking, $loan, 500);

        $response = $this->getJson('/api/v1/metrics/total-liabilities');

        $response->assertOk();
        $this->assertEquals(1500, $response->json('data.value'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/total-liabilities');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }
}