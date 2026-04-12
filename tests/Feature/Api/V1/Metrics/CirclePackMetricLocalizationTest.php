<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CirclePackMetricLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_localized_category_labels_for_financial_visualization(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'locale' => 'ar',
        ]);
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Main Account', 'ar' => 'الحساب الرئيسي'],
        ]);
        $category = Category::factory()->create([
            'user_id' => $user->id,
            'type' => Category::EXPENSES,
            'name' => ['en' => 'Meat', 'ar' => 'اللحوم'],
            'color' => 'red',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 300,
            'transaction_type' => Transaction::TYPE_DEBIT,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/metrics/circle-pack');

        $response->assertOk();

        $expenseGroup = collect($response->json('data.children'))
            ->firstWhere('label', Category::EXPENSES);

        $this->assertNotNull($expenseGroup);
        $this->assertSame('اللحوم', $expenseGroup['children'][0]['label']);
        $this->assertIsString($expenseGroup['children'][0]['label']);
    }
}
