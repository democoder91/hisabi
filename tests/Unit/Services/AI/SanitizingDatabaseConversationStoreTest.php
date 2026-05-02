<?php

use App\Models\User;
use App\Services\AI\SanitizingDatabaseConversationStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('drops orphaned tool calls when replaying a remembered conversation', function () {
    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Broken tool conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'Tests\\FakeAgent',
        'role' => 'assistant',
        'content' => '',
        'attachments' => '[]',
        'tool_calls' => json_encode([
            [
                'id' => 'fc_matched',
                'name' => 'CreateTransactionTool',
                'arguments' => ['amount' => 140],
                'result_id' => 'call_matched',
            ],
            [
                'id' => 'fc_orphaned',
                'name' => 'CreateTransactionTool',
                'arguments' => ['amount' => 185],
                'result_id' => 'call_orphaned',
            ],
        ], JSON_THROW_ON_ERROR),
        'tool_results' => json_encode([
            [
                'id' => 'fc_matched',
                'name' => 'CreateTransactionTool',
                'arguments' => ['amount' => 140],
                'result' => 'Transaction created successfully.',
                'result_id' => 'call_matched',
            ],
        ], JSON_THROW_ON_ERROR),
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $messages = app(SanitizingDatabaseConversationStore::class)->getLatestConversationMessages($conversationId, 10);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[1])->toBeInstanceOf(ToolResultMessage::class)
        ->and($messages[0]->toolCalls)->toHaveCount(1)
        ->and($messages[0]->toolCalls->first()->id)->toBe('fc_matched')
        ->and($messages[0]->toolCalls->first()->resultId)->toBe('call_matched')
        ->and($messages[1]->toolResults)->toHaveCount(1)
        ->and($messages[1]->toolResults->first()->id)->toBe('fc_matched')
        ->and($messages[1]->toolResults->first()->resultId)->toBe('call_matched');
});