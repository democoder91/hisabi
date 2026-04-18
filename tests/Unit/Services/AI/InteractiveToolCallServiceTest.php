<?php

use App\Domains\Category\Models\Category;
use App\Models\User;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('canonicalizes legacy category question ids and enriches their category metadata when reading interaction meta', function () {
    $category = Category::factory()->create([
        'type' => Category::INCOME,
        'name' => [
            'en' => 'Family Support',
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
                    'id' => 'transaction_type',
                    'label' => 'What type of transaction is this?',
                    'type' => 'select',
                    'options' => [
                        ['label' => 'Expense', 'value' => Category::EXPENSES],
                        ['label' => 'Income', 'value' => Category::INCOME],
                    ],
                ],
                [
                    'id' => 'category',
                    'label' => 'Select a category',
                    'type' => 'select',
                    'options' => [
                        ['label' => 'Family Support', 'value' => (string) $category->id],
                    ],
                ],
            ],
        ],
    ]));

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][1]['id'])->toBe('category_id')
        ->and($interaction['questions'][1]['options'][0]['meta']['category_type'])->toBe(Category::INCOME);
});

it('hydrates stale category option values to current ids for a conversation', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'type' => Category::EXPENSES,
        'name' => [
            'en' => 'Dining',
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
                'tool_call_id' => 'tool-call-1',
                'questions' => [
                    [
                        'id' => 'category',
                        'label' => 'Select a category',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Dining', 'value' => '5'],
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($interaction)->not->toBeNull()
        ->and($interaction['questions'][0]['id'])->toBe('category_id')
        ->and($interaction['questions'][0]['options'][0]['value'])->toBe((string) $category->id)
        ->and($interaction['questions'][0]['options'][0]['meta']['legacy_values'])->toBe(['5']);
});
