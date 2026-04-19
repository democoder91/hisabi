<?php

namespace Tests\Feature\Api\V1\Metrics;

use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CirclePackMetricLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_localized_account_labels_for_financial_visualization(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'locale' => 'ar',
        ]);
        $sourceAccount = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Main Account', 'ar' => 'الحساب الرئيسي'],
            'type' => Account::TYPE_ASSET,
        ]);
        $expenseAccount = Account::factory()->create([
            'user_id' => $user->id,
            'name' => ['en' => 'Meat', 'ar' => 'اللحوم'],
            'type' => Account::TYPE_EXPENSE,
        ]);

        \App\Domains\Transaction\Models\Transaction::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'account_id' => $sourceAccount->id,
            'category_id' => null,
            'from_account_id' => $sourceAccount->id,
            'to_account_id' => $expenseAccount->id,
            'amount' => 300,
            'transaction_type' => \App\Domains\Transaction\Models\Transaction::TYPE_DEBIT,
            'currency' => $sourceAccount->currency,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/metrics/circle-pack');

        $response->assertOk();

        $expenseGroup = collect($response->json('data.children'))
            ->firstWhere('label', 'Expenses');

        $this->assertNotNull($expenseGroup);
        $this->assertSame('اللحوم', $expenseGroup['children'][0]['label']);
        $this->assertIsString($expenseGroup['children'][0]['label']);
    }
}
