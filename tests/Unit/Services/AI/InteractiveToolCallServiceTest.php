<?php

use App\Domains\Category\Models\Category;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('enriches legacy pending category options with category type metadata when reading interaction meta', function () {
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
                    'id' => 'category_id',
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
        ->and($interaction['questions'][1]['options'][0]['meta']['category_type'])->toBe(Category::INCOME);
});