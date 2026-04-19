<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;

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

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 300]);
        Transaction::factory()->create(['category_id' => $this->incomeCategory->id, 'amount' => 5000]);

        $response = $this->getJson('/api/v1/metrics/circle-pack?range=current-year');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('children', $data);
    }

    public function test_groups_multiple_categories(): void
    {
        $this->actingAs($this->user);

        $groceryCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Groceries'],
        ]);

        Transaction::factory()->create(['category_id' => $this->expensesCategory->id, 'amount' => 300]);
        Transaction::factory()->create(['category_id' => $groceryCategory->id, 'amount' => 500]);

        $response = $this->getJson('/api/v1/metrics/circle-pack?range=current-year');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('children', $data);
    }

    public function test_returns_empty_children_when_no_data(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/metrics/circle-pack?range=current-year');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('children', $data);
        $this->assertEmpty($data['children']);
    }
}
