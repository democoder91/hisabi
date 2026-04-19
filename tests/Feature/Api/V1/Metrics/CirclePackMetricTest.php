<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class CirclePackMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/circle-pack');
        $response->assertUnauthorized();
    }

    public function test_returns_hierarchical_data(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 300);
        $this->createLedgerTransaction($salary, $checking, 5000);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/circle-pack?from={$from}&to={$to}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('children', $data);
    }

    public function test_groups_multiple_accounts(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $transport = $this->createAccount(['name' => ['en' => 'Transport'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 300);
        $this->createLedgerTransaction($checking, $transport, 500);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/circle-pack?from={$from}&to={$to}");

        $response->assertOk();
        $data = $response->json('data');
        $expensesGroup = collect($data['children'])->firstWhere('label', 'Expenses');

        $this->assertNotNull($expensesGroup);
        $this->assertCount(2, $expensesGroup['children']);
    }

    public function test_returns_empty_children_when_no_data(): void
    {
        $this->actingAs($this->user);

        $from = now()->startOfYear()->toDateString();
        $to = now()->endOfYear()->toDateString();

        $response = $this->getJson("/api/v1/metrics/circle-pack?from={$from}&to={$to}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('children', $data);
        $this->assertEmpty($data['children']);
    }
}
