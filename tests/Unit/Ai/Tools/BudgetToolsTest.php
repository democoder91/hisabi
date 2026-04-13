<?php

use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\EditBudgetTool;
use App\Domains\Budget\Models\Budget;
use App\Domains\Category\Models\Category;
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

    $category = Category::query()->create([
        'user_id' => $user->id,
        'type' => Category::EXPENSES,
        'name' => ['en' => 'Groceries', 'ar' => null],
        'color' => 'green',
        'icon' => 'shopping-cart',
    ]);

    $this->actingAs($user);

    $result = app(CreateBudgetTool::class)->handle(new Request([
        'name_en' => 'Groceries Budget',
        'amount' => 2000,
        'start_at' => '2026-04-13',
        'period' => 1,
        'reoccurrence' => Budget::MONTHLY,
        'category_ids' => [$category->id],
    ]));

    $budget = Budget::query()->latest('id')->first();

    expect($budget)->not->toBeNull();
    expect($budget->currency)->toBe('EGP');
    expect($result)->toContain('Budget created successfully');
});

it('keeps the existing budget currency when the AI edits a budget without sending currency', function () {
    $user = createAiBudgetTestUser('budget-edit@example.com', 'USD');

    $category = Category::query()->create([
        'user_id' => $user->id,
        'type' => Category::EXPENSES,
        'name' => ['en' => 'Groceries', 'ar' => null],
        'color' => 'green',
        'icon' => 'shopping-cart',
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
    $budget->categories()->attach($category->id);

    $this->actingAs($user);

    $result = app(EditBudgetTool::class)->handle(new Request([
        'budget_id' => $budget->id,
        'amount' => 2000,
    ]));

    expect($budget->fresh()->currency)->toBe('EGP');
    expect($budget->fresh()->amount)->toBe(2000.0);
    expect($result)->toContain('Budget updated successfully');
});
