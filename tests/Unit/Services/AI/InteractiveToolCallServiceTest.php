<?php

use App\Domains\Account\Models\Account;
use App\Models\User;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Account::forgetCachedTypeColumnSupport();
});

afterEach(function () {
    Account::forgetCachedTypeColumnSupport();
});

it('canonicalizes legacy account question ids and enriches their account metadata when reading interaction meta', function () {
    $account = Account::factory()->create([
        'type' => Account::TYPE_ASSET,
        'name' => [
            'en' => 'Checking',
            'ar' => null,
        ],
    ]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromMeta(json_encode([
        'interaction' => [
            'status' => InteractiveToolCallService::STATUS_PENDING,
            'tool_name' => InteractiveToolCallService::TOOL_NAME,
            'tool_call_id' => 'tool-call-1',
            'questions' => [
                [
                    'id' => 'account',
                    'label' => 'Select an account',
                    'type' => 'select',
                    'options' => [
                        ['label' => 'Checking', 'value' => (string) $account->id],
                    ],
                ],
            ],
        ],
    ]));

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('account_id')
        ->and($interaction['questions'][0]['options'][0]['meta']['account_type'])->toBe(Account::TYPE_ASSET)
        ->and($interaction['questions'][0]['options'][0]['meta']['owner_id'])->toBe((string) $account->user_id);
});

it('hydrates stale account option values to current ids for a conversation', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Checking',
            'ar' => null,
        ],
        'type' => Account::TYPE_ASSET,
    ]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-2',
                'questions' => [
                    [
                        'id' => 'account',
                        'label' => 'Select an account',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Checking', 'value' => '5'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('account_id')
        ->and($interaction['questions'][0]['options'][0]['value'])->toBe((string) $account->id)
        ->and($interaction['questions'][0]['options'][0]['meta']['legacy_values'])->toBe(['5'])
        ->and($interaction['questions'][0]['options'][0]['meta']['account_type'])->toBe(Account::TYPE_ASSET)
        ->and($interaction['questions'][0]['options'][0]['meta']['owner_id'])->toBe((string) $user->id);
});

it('canonicalizes legacy accounts multiselect ids and refreshes current account options', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $checkingAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Checking',
            'ar' => null,
        ],
    ]);

    $savingsAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Savings',
            'ar' => null,
        ],
    ]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-3',
                'questions' => [
                    [
                        'id' => 'accounts',
                        'label' => 'Select accounts',
                        'type' => 'multiselect',
                        'options' => [
                            ['label' => 'Checking', 'value' => '5'],
                            ['label' => 'Savings', 'value' => '6'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('account_ids')
        ->and($interaction['questions'][0]['options'][0]['value'])->toBe((string) $checkingAccount->id)
        ->and($interaction['questions'][0]['options'][0]['meta']['legacy_values'])->toBe(['5'])
        ->and($interaction['questions'][0]['options'][1]['value'])->toBe((string) $savingsAccount->id)
        ->and($interaction['questions'][0]['options'][1]['meta']['legacy_values'])->toBe(['6']);
});

it('hydrates current account options for custom account question ids', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $transportationAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Transportation',
            'ar' => null,
        ],
        'type' => Account::TYPE_EXPENSE,
    ]);

    $cashAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Cash',
            'ar' => null,
        ],
        'type' => Account::TYPE_ASSET,
    ]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-custom-account-id',
                'questions' => [
                    [
                        'id' => 'transportation_account_id',
                        'label' => 'Select the account for "Transportation" expenses:',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Create a new account', 'value' => '__create_new__'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('transportation_account_id')
        ->and(collect($interaction['questions'][0]['options'])->pluck('value')->all())->toBe([
            (string) $cashAccount->id,
            (string) $transportationAccount->id,
            '__create_new__',
        ])
        ->and(collect($interaction['questions'][0]['options'])->firstWhere('value', (string) $transportationAccount->id)['meta']['account_type'])->toBe(Account::TYPE_EXPENSE)
        ->and(collect($interaction['questions'][0]['options'])->firstWhere('value', (string) $cashAccount->id)['meta']['account_type'])->toBe(Account::TYPE_ASSET);
});

it('canonicalizes confirm source account questions and refreshes them to current editable account ids', function () {
    /** @var User $user */
    $user = User::factory()->create();

    Account::factory()->create();

    $cashAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Cash Account',
            'ar' => null,
        ],
        'type' => Account::TYPE_ASSET,
    ]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-confirm-source-account',
                'questions' => [
                    [
                        'id' => 'confirm_source_account',
                        'label' => 'Please confirm the source account for these transactions.',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Cash Account', 'value' => '1'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('from_account_id')
        ->and($interaction['questions'][0]['options'][0]['value'])->toBe((string) $cashAccount->id)
        ->and($interaction['questions'][0]['options'][0]['meta']['legacy_values'])->toBe(['1'])
        ->and($interaction['questions'][0]['options'][0]['meta']['account_type'])->toBe(Account::TYPE_ASSET);
});

it('refreshes memo-specific destination account prompts with current editable accounts only', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $editableDestination = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Food & Groceries',
            'ar' => null,
        ],
        'type' => Account::TYPE_EXPENSE,
    ]);

    $owner = User::factory()->create();
    $viewOnlyDestination = Account::factory()->create([
        'user_id' => $owner->id,
        'name' => [
            'en' => 'Food & Groceries Shared',
            'ar' => null,
        ],
        'type' => Account::TYPE_EXPENSE,
    ]);
    $viewOnlyDestination->sharedUsers()->attach($user->id, ['permission_level' => Account::PERMISSION_VIEW]);

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-destination-account-memo',
                'questions' => [
                    [
                        'id' => 'destination_account_khal_wa_shay',
                        'label' => 'Please select a destination account for khal wa shay.',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Food & Groceries', 'value' => '54'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    $optionValues = collect($interaction['questions'][0]['options'])->pluck('value')->all();

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('destination_account_khal_wa_shay')
        ->and($optionValues)->toContain((string) $editableDestination->id)
        ->and($optionValues)->not->toContain((string) $viewOnlyDestination->id)
        ->and(last($optionValues))->toBe('__create_new__');
});

it('does not select the type column when refreshing account options for a legacy schema', function () {
    $user = User::factory()->create();

    Schema::shouldReceive('hasColumn')
        ->andReturnUsing(fn(string $table, string $column): bool => $column !== 'type');

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => [
            'en' => 'Cash',
            'ar' => null,
        ],
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromConversation(
        (string) Str::uuid(),
        $user,
        json_encode([
            'interaction' => [
                'status' => InteractiveToolCallService::STATUS_PENDING,
                'tool_name' => InteractiveToolCallService::TOOL_NAME,
                'tool_call_id' => 'tool-call-legacy-refresh',
                'questions' => [
                    [
                        'id' => 'account',
                        'label' => 'Select an account',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Cash', 'value' => '9'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn(string $query) => strtolower($query));

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['options'][0]['value'])->toBe((string) $account->id)
        ->and($interaction['questions'][0]['options'][0]['meta']['account_type'])->toBe(Account::TYPE_ASSET)
        ->and($queries->contains(fn(string $query) => str_contains($query, 'select "id", "name", "type", "user_id" from "accounts"')))->toBeFalse();
});

it('does not select the type column when enriching account option metadata for a legacy schema', function () {
    $account = Account::factory()->create([
        'name' => [
            'en' => 'Checking',
            'ar' => null,
        ],
    ]);

    Account::forgetCachedTypeColumnSupport();

    Schema::shouldReceive('hasColumn')
        ->with('accounts', 'type')
        ->andReturn(false);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $interaction = app(InteractiveToolCallService::class)->pendingInteractionFromMeta(json_encode([
        'interaction' => [
            'status' => InteractiveToolCallService::STATUS_PENDING,
            'tool_name' => InteractiveToolCallService::TOOL_NAME,
            'tool_call_id' => 'tool-call-legacy-meta',
            'questions' => [
                [
                    'id' => 'account',
                    'label' => 'Select an account',
                    'type' => 'select',
                    'options' => [
                        ['label' => 'Checking', 'value' => (string) $account->id],
                    ],
                ],
            ],
        ],
    ]));

    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn(string $query) => strtolower($query));

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['options'][0]['meta']['account_type'])->toBe(Account::TYPE_ASSET)
        ->and($queries->contains(fn(string $query) => str_contains($query, 'select "id", "type", "user_id" from "accounts"')))->toBeFalse();
});
