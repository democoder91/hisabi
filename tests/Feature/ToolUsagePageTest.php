<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows super users to inspect tool usage logs for all users', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $regularUser */
    $regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
    ]);

    $conversationId = (string) Str::uuid();
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $regularUser->id,
        'title' => 'Review spending',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => $regularUser->id,
        'agent' => 'App\\Ai\\Agents\\HisabiAgent',
        'role' => 'assistant',
        'content' => 'I checked your accounts and summarized the balances.',
        'attachments' => '[]',
        'tool_calls' => json_encode([
            [
                'id' => 'call_1',
                'name' => 'list_accounts',
                'arguments' => '{"limit":5}',
                'result_id' => 'result_1',
            ],
        ]),
        'tool_results' => json_encode([
            [
                'id' => 'call_1',
                'name' => 'list_accounts',
                'arguments' => '{"limit":5}',
                'result' => 'Found 2 accounts.',
                'result_id' => 'result_1',
            ],
        ]),
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    actingAs($superUser);

    $response = get(route('ai.tool-usage'));

    $response->assertOk();
    $response->assertSee('&quot;component&quot;:&quot;Ai\/ToolUsage&quot;', false);
    $response->assertSee('regular@example.com');
    $response->assertSee('list_accounts');
    $response->assertSee('Found 2 accounts.');
    $response->assertSee($conversationId);
});

it('forbids non super users from opening the tool usage page', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'is_super' => false,
    ]);

    actingAs($user);

    get(route('ai.tool-usage'))->assertForbidden();
});

it('filters tool usage logs by tool name', function () {
    /** @var User $superUser */
    $superUser = User::factory()->create([
        'is_super' => true,
    ]);

    /** @var User $regularUser */
    $regularUser = User::factory()->create();
    $now = now();

    $conversationIds = [
        (string) Str::uuid(),
        (string) Str::uuid(),
    ];

    foreach ($conversationIds as $index => $conversationId) {
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $regularUser->id,
            'title' => 'Conversation ' . ($index + 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationIds[0],
            'user_id' => $regularUser->id,
            'agent' => 'App\\Ai\\Agents\\HisabiAgent',
            'role' => 'assistant',
            'content' => 'Created a transaction for coffee.',
            'attachments' => '[]',
            'tool_calls' => json_encode([
                ['id' => 'call_tx', 'name' => 'create_transaction', 'arguments' => '{"amount":18}'],
            ]),
            'tool_results' => json_encode([
                ['id' => 'call_tx', 'name' => 'create_transaction', 'arguments' => '{"amount":18}', 'result' => 'Transaction created.'],
            ]),
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationIds[1],
            'user_id' => $regularUser->id,
            'agent' => 'App\\Ai\\Agents\\HisabiAgent',
            'role' => 'assistant',
            'content' => 'Listed the accounts.',
            'attachments' => '[]',
            'tool_calls' => json_encode([
                ['id' => 'call_accounts', 'name' => 'list_accounts', 'arguments' => '{"limit":10}'],
            ]),
            'tool_results' => json_encode([
                ['id' => 'call_accounts', 'name' => 'list_accounts', 'arguments' => '{"limit":10}', 'result' => 'Found accounts.'],
            ]),
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    actingAs($superUser);

    $response = get(route('ai.tool-usage', ['tool' => 'create_transaction']));

    $response->assertOk();
    $response->assertSee('create_transaction');
    $response->assertDontSee('list_accounts');
});
