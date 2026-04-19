<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class AverageTransactionMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/average-transaction');
        $response->assertUnauthorized();
    }

    public function test_returns_average_transaction_by_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 100);
        $this->createLedgerTransaction($checking, $food, 200);
        $this->createLedgerTransaction($checking, $food, 300);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/average-transaction?from={$from}&to={$to}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $foodAccount = collect($items)->firstWhere('label', 'Food');
        $this->assertEquals(200, $foodAccount['value']);
    }

    public function test_groups_by_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($salary, $checking, 5000);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/average-transaction?from={$from}&to={$to}");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/average-transaction?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}
