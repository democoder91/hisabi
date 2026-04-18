<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows the authenticated user conversations and selected thread', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    /** @var User $user */
    $user = User::factory()->create();

    /** @var User $otherUser */
    $otherUser = User::factory()->create();

    $olderConversationId = (string) Str::uuid();
    $activeConversationId = (string) Str::uuid();
    $otherConversationId = (string) Str::uuid();
    $now = now();

    DB::table('agent_conversations')->insert([
        [
            'id' => $olderConversationId,
            'user_id' => $user->id,
            'title' => 'Older finance review',
            'created_at' => $now->copy()->subDay(),
            'updated_at' => $now->copy()->subHours(2),
        ],
        [
            'id' => $activeConversationId,
            'user_id' => $user->id,
            'title' => 'Coffee spending trends',
            'created_at' => $now->copy()->subHours(3),
            'updated_at' => $now,
        ],
        [
            'id' => $otherConversationId,
            'user_id' => $otherUser->id,
            'title' => 'Other user conversation',
            'created_at' => $now->copy()->subHours(2),
            'updated_at' => $now->copy()->subHour(),
        ],
    ]);

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $activeConversationId,
            'user_id' => $user->id,
            'agent' => 'App\\Ai\\Agents\\HisabiAgent',
            'role' => 'user',
            'content' => 'What did I spend the most on last week?',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now->copy()->subMinutes(2),
        ],
        [
            'id' => (string) Str::uuid(),
            'conversation_id' => $activeConversationId,
            'user_id' => $user->id,
            'agent' => 'App\\Ai\\Agents\\HisabiAgent',
            'role' => 'assistant',
            'content' => 'Coffee was your top expense last week.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $now->copy()->subMinute(),
            'updated_at' => $now->copy()->subMinute(),
        ],
    ]);

    actingAs($user);

    get(route('ai.chat', ['conversation_id' => $activeConversationId]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ai/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $activeConversationId)
            ->where('conversations.1.id', $olderConversationId)
            ->where('activeConversation.id', $activeConversationId)
            ->where('activeConversation.title', 'Coffee spending trends')
            ->has('activeConversation.messages', 2)
            ->where('activeConversation.messages.0.role', 'user')
            ->where('activeConversation.messages.1.content', 'Coffee was your top expense last week.'));
});

it('returns not found when a user opens another users conversation', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    /** @var User $user */
    $user = User::factory()->create();

    /** @var User $otherUser */
    $otherUser = User::factory()->create();

    $conversationId = (string) Str::uuid();
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $otherUser->id,
        'title' => 'Private thread',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    actingAs($user);

    get(route('ai.chat', ['conversation_id' => $conversationId]))->assertNotFound();
});
