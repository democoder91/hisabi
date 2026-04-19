<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class TotalAssetsMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/total-assets');
        $response->assertUnauthorized();
    }

    public function test_returns_net_asset_movement(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($checking, $food, 500);

        $response = $this->getJson('/api/v1/metrics/total-assets');

        $response->assertOk();
        $this->assertEquals(4500, $response->json('data.value'));
    }

    public function test_returns_zero_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/total-assets');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.value'));
    }
}