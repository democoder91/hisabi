<?php

use App\Domains\Account\Models\Account;
use App\Models\UploadedFile;
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

it('includes uploaded files when reopening a conversation', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();
    $messageId = (string) Str::uuid();
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Receipt review',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'App\\Ai\\Agents\\HisabiAgent',
        'role' => 'user',
        'content' => 'Please scan this receipt.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    UploadedFile::query()->create([
        'user_id' => $user->id,
        'attachable_type' => 'App\\Models\\AgentConversationMessage',
        'attachable_id' => $messageId,
        'purpose' => 'receipt',
        'disk' => 'local',
        'path' => 'private/ai-uploads/test/receipt.png',
        'original_name' => 'receipt.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size_bytes' => 1024,
        'visibility' => 'private',
        'custom_attributes' => [
            'source' => 'seed',
        ],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    actingAs($user);

    get(route('ai.chat', ['conversation_id' => $conversationId]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ai/Index')
            ->where('activeConversation.id', $conversationId)
            ->where('activeConversation.messages.0.uploads.0.purpose', 'receipt')
            ->where('activeConversation.messages.0.uploads.0.mime_type', 'image/png')
            ->where('activeConversation.messages.0.uploads.0.file_type_family', 'image')
            ->where('activeConversation.messages.0.uploads.0.custom_attributes.source', 'seed'));
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

it('refreshes stale pending account options when reopening a conversation', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    /** @var User $user */
    $user = User::factory()->create();

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => Account::TYPE_ASSET,
        'name' => [
            'en' => 'Checking',
            'ar' => null,
        ],
    ]);

    $conversationId = (string) Str::uuid();
    $toolCallId = (string) Str::uuid();
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Pending thread',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'App\\Ai\\Agents\\HisabiAgent',
        'role' => 'assistant',
        'content' => 'Please provide the requested details to continue.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => json_encode([
            'interaction' => [
                'status' => 'pending',
                'tool_name' => 'ask_user_for_input',
                'tool_call_id' => $toolCallId,
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
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    actingAs($user);

    get(route('ai.chat', ['conversation_id' => $conversationId]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ai/Index')
            ->where('activeConversation.id', $conversationId)
            ->where('activeConversation.messages.0.interaction.questions.0.id', 'account_id')
            ->where('activeConversation.messages.0.interaction.questions.0.options.0.value', (string) $account->id)
            ->where('activeConversation.messages.0.interaction.questions.0.options.0.meta.account_type', Account::TYPE_ASSET)
            ->where('activeConversation.messages.0.interaction.questions.0.options.0.meta.owner_id', (string) $user->id)
            ->where('activeConversation.messages.0.interaction.questions.0.options.0.meta.legacy_values.0', '5'));
});
