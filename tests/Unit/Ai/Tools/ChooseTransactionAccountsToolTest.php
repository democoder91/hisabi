<?php

namespace Tests\Unit\Ai\Tools;

use App\Ai\Agents\TransactionAccountChoiceAgent;
use App\Ai\Tools\ChooseTransactionAccountsTool;
use App\Domains\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ChooseTransactionAccountsToolTest extends TestCase
{
    use RefreshDatabase;

    private ChooseTransactionAccountsTool $tool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->tool = new ChooseTransactionAccountsTool();
    }

    public function test_it_validates_that_memos_are_required(): void
    {
        $result = $this->tool->handle(new Request([]));

        $this->assertStringContainsString('Unable to choose destination accounts for the transaction memos yet:', $result);
        $this->assertStringContainsString('The memos field is required.', $result);
    }

    public function test_it_builds_an_accessible_account_payload_and_normalizes_unmatched_results(): void
    {
        $groceriesAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Groceries', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);

        $salaryAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'name' => ['en' => 'Salary', 'ar' => null],
            'type' => Account::TYPE_INCOME,
        ]);

        $otherUser = User::factory()->create();

        $hiddenAccount = Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => ['en' => 'Hidden Rent', 'ar' => null],
            'type' => Account::TYPE_EXPENSE,
        ]);

        TransactionAccountChoiceAgent::fake([
            function (string $prompt) use ($groceriesAccount, $salaryAccount, $hiddenAccount): array {
                $this->assertStringContainsString('"memos":["Payroll","Groceries at Carrefour"]', $prompt);
                $this->assertStringContainsString('"id":'.$groceriesAccount->id, $prompt);
                $this->assertStringContainsString('"name":"Groceries"', $prompt);
                $this->assertStringContainsString('"type":"expense"', $prompt);
                $this->assertStringContainsString('"id":'.$salaryAccount->id, $prompt);
                $this->assertStringNotContainsString('"id":'.$hiddenAccount->id, $prompt);
                $this->assertStringNotContainsString('Hidden Rent', $prompt);

                return [
                    'matches' => [
                        [
                            'memo' => 'Groceries at Carrefour',
                            'account_id' => $groceriesAccount->id,
                        ],
                        [
                            'memo' => 'Ignored invalid id',
                            'account_id' => 999999,
                        ],
                    ],
                ];
            },
        ])->preventStrayPrompts();

        $result = $this->tool->handle(new Request([
            'memos' => ['Payroll', 'Groceries at Carrefour'],
        ]));

        $decoded = json_decode($result, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([
            'matches' => [
                [
                    'memo' => 'Payroll',
                    'account_id' => null,
                ],
                [
                    'memo' => 'Groceries at Carrefour',
                    'account_id' => $groceriesAccount->id,
                ],
            ],
        ], $decoded);
    }
}