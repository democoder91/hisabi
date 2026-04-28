<?php

use App\Models\BillingGrantAudit;
use App\Models\BillingProduct;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('allows super users to open the billing user access page', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
    config()->set('ai.costs.providers.openai.gpt-4o', [
        'input_per_million' => 1,
        'output_per_million' => 2,
        'cache_write_input_per_million' => 0,
        'cache_read_input_per_million' => 0,
        'reasoning_per_million' => 0,
    ]);

    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create();

    createConversationWithCosts($targetUser, 'Budget plan', 'conversation-budget', [
        [
            'role' => 'user',
            'content' => 'How much did I spend?',
        ],
        [
            'role' => 'assistant',
            'content' => 'You spent 500 AED.',
            'usage' => [
                'prompt_tokens' => 1000,
                'completion_tokens' => 500,
            ],
            'meta' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ],
        ],
    ]);

    actingAs($superUser);

    get(route('billing.manage.users'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Billing/Users')
            ->has('users', 2)
            ->where('users.0.email', $targetUser->email)
            ->where('users.0.totalConversationCost', 0.002)
            ->where('conversationCostCurrency', 'USD')
            ->has('grantOptions.creditPackages')
            ->has('grantOptions.subscriptionPlans')
            ->where('pagination.total', 2));
});

it('shows conversation cost details for a selected user', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
    config()->set('ai.costs.providers.openai.gpt-4o', [
        'input_per_million' => 1,
        'output_per_million' => 2,
        'cache_write_input_per_million' => 0,
        'cache_read_input_per_million' => 0,
        'reasoning_per_million' => 0,
    ]);

    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create();

    createConversationWithCosts($targetUser, 'Plan groceries', 'conversation-groceries', [
        [
            'role' => 'user',
            'content' => 'Summarize my grocery spending',
        ],
        [
            'role' => 'assistant',
            'content' => 'Groceries were 200 AED.',
            'usage' => [
                'prompt_tokens' => 1000,
                'completion_tokens' => 500,
            ],
            'meta' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ],
        ],
        [
            'role' => 'user',
            'content' => 'What about restaurants?',
        ],
        [
            'role' => 'assistant',
            'content' => 'Restaurants were 100 AED.',
            'usage' => [
                'prompt_tokens' => 500,
                'completion_tokens' => 250,
            ],
            'meta' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-2024-08-06',
            ],
        ],
    ]);

    createConversationWithCosts($targetUser, 'Travel budget', 'conversation-travel', [
        [
            'role' => 'user',
            'content' => 'Create a travel budget',
        ],
        [
            'role' => 'assistant',
            'content' => 'Here is a travel budget.',
            'usage' => [
                'prompt_tokens' => 1000,
                'completion_tokens' => 500,
            ],
            'meta' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
            ],
        ],
    ]);

    actingAs($superUser);

    get(route('billing.manage.users.conversation-costs', $targetUser))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Billing/UserConversationCosts')
            ->where('user.email', $targetUser->email)
            ->where('summary.totalCost', 0.005)
            ->where('summary.totalTurns', 3)
            ->where('summary.conversationCount', 2)
            ->has('conversations', 2)
            ->where('conversations.0.id', 'conversation-groceries')
            ->where('conversations.0.turns', 2)
            ->where('conversations.0.cost', 0.003)
            ->where('conversations.1.id', 'conversation-travel')
            ->where('conversations.1.turns', 1)
            ->where('conversations.1.cost', 0.002));
});

it('forbids non super users from opening the billing user access page', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => false,
    ]);

    actingAs($user);

    get(route('billing.manage.users'))->assertForbidden();
});

it('allows a super user to grant a credit package and records an audit', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create([
        'available_credits' => 5,
    ]);

    $creditPackage = BillingProduct::query()->where('slug', 'starter-100')->firstOrFail();

    actingAs($superUser);

    post(route('billing.manage.users.grants.store', $targetUser), [
        'billing_product_id' => $creditPackage->id,
    ])->assertRedirect();

    expect($targetUser->fresh()->available_credits)->toBe(5 + (int) $creditPackage->credits);

    assertDatabaseHas('billing_grant_audits', [
        'admin_user_id' => $superUser->id,
        'target_user_id' => $targetUser->id,
        'billing_product_id' => $creditPackage->id,
        'grant_type' => 'credits',
    ]);

    $audit = BillingGrantAudit::query()->latest()->firstOrFail();

    expect($audit->product_snapshot['slug'])->toBe('starter-100');
    expect($audit->old_values['available_credits'])->toBe(5);
    expect($audit->new_values['available_credits'])->toBe(5 + (int) $creditPackage->credits);
});

it('extends an existing subscription when a super user grants a subscription plan', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $targetUser */
    $targetUser = User::factory()->create([
        'available_credits' => 10,
        'trial_ends_at' => now()->addDays(3),
    ]);

    $initialRenewal = now()->addDays(7)->startOfSecond();

    Subscription::query()->create([
        'user_id' => $targetUser->id,
        'plan_name' => 'Existing Plan',
        'status' => 'active',
        'paymob_order_id' => null,
        'renews_at' => $initialRenewal,
    ]);

    $subscriptionPlan = BillingProduct::query()->where('slug', 'pro-monthly')->firstOrFail();

    actingAs($superUser);

    post(route('billing.manage.users.grants.store', $targetUser), [
        'billing_product_id' => $subscriptionPlan->id,
    ])->assertRedirect();

    $updatedUser = $targetUser->fresh();
    $updatedSubscription = Subscription::query()->where('user_id', $targetUser->id)->firstOrFail();
    $expectedRenewal = $initialRenewal->copy()->addDays((int) $subscriptionPlan->renews_in_days);

    expect($updatedUser->available_credits)->toBe(10 + (int) $subscriptionPlan->credits);
    expect($updatedUser->trial_ends_at)->toBeNull();
    expect($updatedSubscription->plan_name)->toBe($subscriptionPlan->localizedName($targetUser->locale));
    expect($updatedSubscription->renews_at->toDateTimeString())->toBe($expectedRenewal->toDateTimeString());

    $audit = BillingGrantAudit::query()->latest()->firstOrFail();

    expect($audit->grant_type)->toBe('subscription');
    expect($audit->old_values['subscription']['plan_name'])->toBe('Existing Plan');
    expect($audit->new_values['subscription']['plan_name'])->toBe($subscriptionPlan->localizedName($targetUser->locale));
    expect($audit->new_values['available_credits'])->toBe(10 + (int) $subscriptionPlan->credits);
});

function createConversationWithCosts(User $user, string $title, string $conversationId, array $messages): void
{
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($messages as $index => $message) {
        DB::table('agent_conversation_messages')->insert([
            'id' => sprintf('%s-message-%d', $conversationId, $index + 1),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'App\\Ai\\Agents\\HisabiAgent',
            'role' => $message['role'],
            'content' => $message['content'],
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => json_encode($message['usage'] ?? []) ?: '[]',
            'meta' => json_encode($message['meta'] ?? []) ?: '[]',
            'created_at' => now()->addSeconds($index),
            'updated_at' => now()->addSeconds($index),
        ]);
    }
}
