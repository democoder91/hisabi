<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;

class AccountStatsMetricTest extends MetricsTestCase
{
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics/account-stats');
        $response->assertUnauthorized();
    }

    public function test_returns_most_used_account(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($checking, $food, 200);
        $this->createLedgerTransaction($checking, $food, 150);

        $response = $this->getJson('/api/v1/metrics/account-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('mostUsedAccount', $data);
        $this->assertEquals('Checking', $data['mostUsedAccount']['name']);
        $this->assertEquals(3, $data['mostUsedAccount']['count']);
    }

    public function test_returns_highest_spending_account(): void
    {
        $this->actingAs($this->user);

        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);
        $food = $this->createAccount(['name' => ['en' => 'Food'], 'type' => Account::TYPE_EXPENSE]);
        $transport = $this->createAccount(['name' => ['en' => 'Transport'], 'type' => Account::TYPE_EXPENSE]);

        $this->createLedgerTransaction($checking, $food, 500);
        $this->createLedgerTransaction($checking, $transport, 300);

        $response = $this->getJson('/api/v1/metrics/account-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('highestSpendingAccount', $data);
        $this->assertEquals('Food', $data['highestSpendingAccount']['name']);
        $this->assertEquals(500, $data['highestSpendingAccount']['amount']);
    }

    public function test_returns_highest_income_account(): void
    {
        $this->actingAs($this->user);

        $salary = $this->createAccount(['name' => ['en' => 'Salary'], 'type' => Account::TYPE_INCOME]);
        $freelance = $this->createAccount(['name' => ['en' => 'Freelance'], 'type' => Account::TYPE_INCOME]);
        $checking = $this->createAccount(['name' => ['en' => 'Checking'], 'type' => Account::TYPE_ASSET]);

        $this->createLedgerTransaction($salary, $checking, 5000);
        $this->createLedgerTransaction($freelance, $checking, 2000);

        $response = $this->getJson('/api/v1/metrics/account-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('highestIncomeAccount', $data);
        $this->assertEquals('Salary', $data['highestIncomeAccount']['name']);
        $this->assertEquals(5000, $data['highestIncomeAccount']['amount']);
    }

    public function test_returns_nulls_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/account-stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNull($data['mostUsedAccount']);
        $this->assertNull($data['highestSpendingAccount']);
        $this->assertNull($data['highestIncomeAccount']);
    }
}