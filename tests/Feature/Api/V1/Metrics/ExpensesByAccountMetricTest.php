<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class ExpensesByAccountMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/expenses-by-account');
        $response->assertUnauthorized();
    }

    public function test_returns_grouped_data(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 500);

        $response = $this->getJson('/api/v1/metrics/expenses-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals('Food', $items[0]['label']);
        $this->assertEquals(500, $items[0]['value']);
    }

    public function test_excludes_income_movements(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($salary, $checking, 5000);

        $response = $this->getJson('/api/v1/metrics/expenses-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('Food', $items[0]['label']);
    }

    public function test_groups_multiple_expense_accounts(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $transport = $this->createAccount(['name' => ['en' => 'Transport'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($checking, $transport, 300);

        $response = $this->getJson('/api/v1/metrics/expenses-by-account');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_orders_by_value_descending(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $transport = $this->createAccount(['name' => ['en' => 'Transport'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 300);
        $this->createLedgerTransaction($checking, $transport, 500);

        $response = $this->getJson('/api/v1/metrics/expenses-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertEquals('Transport', $items[0]['label']);
        $this->assertEquals('Food', $items[1]['label']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/expenses-by-account');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}