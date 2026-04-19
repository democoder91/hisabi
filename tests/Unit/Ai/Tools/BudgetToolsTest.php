<?php

use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\EditBudgetTool;
use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createAiBudgetTestUser(string $email, string $defaultCurrency): User
{
    return User::query()->create([
        'name' => 'AI Budget Tester',
        'email' => $email,
        'password' => Hash::make('password'),
        'default_currency' => $defaultCurrency,
        'locale' => 'en',
        'available_credits' => 10,
        'trial_ends_at' => now()->addDays(14),
        'is_super' => false,
    ]);
}

it('creates a budget with the authenticated user default currency when the AI omits currency', function () {
    $user = createAiBudgetTestUser('budget-create@example.com', 'EGP');

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_EXPENSE,
        'name' => ['en' => 'Groceries', 'ar' => null],
        'currency' => 'EGP',
    ]);

    $this->actingAs($user);

    $result = app(CreateBudgetTool::class)->handle(new Request([
        'name_en' => 'Groceries Budget',
        'amount' => 2000,
        'start_at' => '2026-04-13',
        'period' => 1,
        'reoccurrence' => Budget::MONTHLY,
        'account_ids' => [$account->id],
    ]));

    $budget = Budget::query()->with('accounts')->latest('id')->first();

    expect($budget)->not->toBeNull();
    expect($budget->currency)->toBe('EGP');
    expect($budget->accounts->pluck('id')->all())->toBe([$account->id]);
    expect($result)->toContain('Budget created successfully');
});

it('requires account ids when the ai creates a budget', function () {
    $user = createAiBudgetTestUser('budget-create-validation@example.com', 'EGP');

    $this->actingAs($user);

    expect(fn() => app(CreateBudgetTool::class)->handle(new Request([
        'name_en' => 'Groceries Budget',
        'amount' => 2000,
        'start_at' => '2026-04-13',
        'period' => 1,
        'reoccurrence' => Budget::MONTHLY,
        'category_ids' => [99],
    ])))->toThrow(RuntimeException::class, 'The account ids field is required.');
});

it('keeps the existing budget currency when the AI edits a budget without sending currency', function () {
    $user = createAiBudgetTestUser('budget-edit@example.com', 'USD');

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_EXPENSE,
        'name' => ['en' => 'Groceries', 'ar' => null],
        'currency' => 'EGP',
    ]);

    $budget = Budget::query()->create([
        'user_id' => $user->id,
        'name' => ['en' => 'Groceries Budget', 'ar' => null],
        'amount' => 1500,
        'currency' => 'EGP',
        'start_at' => '2026-04-01',
        'end_at' => null,
        'period' => 1,
        'reoccurrence' => Budget::MONTHLY,
        'saving' => false,
    ]);
    $budget->accounts()->attach($account->id);

    $this->actingAs($user);

    $result = app(EditBudgetTool::class)->handle(new Request([
        'budget_id' => $budget->id,
        'amount' => 2000,
    ]));

    expect($budget->fresh()->currency)->toBe('EGP');
    expect($budget->fresh()->amount)->toBe(2000.0);
    expect($budget->fresh()->accounts()->pluck('accounts.id')->all())->toBe([$account->id]);
    expect($result)->toContain('Budget updated successfully');
});
