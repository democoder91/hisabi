<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class IncomeByAccountMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/income-by-account');
        $response->assertUnauthorized();
    }

    public function test_returns_grouped_data(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000);

        $response = $this->getJson('/api/v1/metrics/income-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertEquals('Salary', $items[0]['label']);
        $this->assertEquals(5000, $items[0]['value']);
    }

    public function test_excludes_expense_movements(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($checking, $food, 500);

        $response = $this->getJson('/api/v1/metrics/income-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('Salary', $items[0]['label']);
    }

    public function test_groups_multiple_income_accounts(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $freelance = $this->createAccount(['name' => ['en' => 'Freelance'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($freelance, $checking, 1200);

        $response = $this->getJson('/api/v1/metrics/income-by-account');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_orders_by_value_descending(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $freelance = $this->createAccount(['name' => ['en' => 'Freelance'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 3000);
        $this->createLedgerTransaction($freelance, $checking, 5000);

        $response = $this->getJson('/api/v1/metrics/income-by-account');

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertEquals('Freelance', $items[0]['label']);
        $this->assertEquals('Salary', $items[1]['label']);
    }

    public function test_returns_empty_array_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/income-by-account');

        $response->assertOk();
        $this->assertIsArray($response->json('data.items'));
        $this->assertEmpty($response->json('data.items'));
    }
}