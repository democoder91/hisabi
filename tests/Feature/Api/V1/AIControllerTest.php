<?php

namespace Tests\Feature\Api\V1;

use App\Ai\Agents\HisabiAgent;
use App\Ai\Exceptions\PendingUserInputToolCall;
use App\Domains\Account\Models\Account;
use App\Http\Commands\AI\ChatCommand\ChatCommand;
use App\Http\Commands\AI\ChatCommand\ChatCommandHandler;
use App\Http\Commands\AI\ChatCommand\ChatCommandResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Exceptions\RateLimitedException;
use Mockery;
use Tests\TestCase;

class AIControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'available_credits' => 5,
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_it_validates_messages_are_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages']);
    }

    public function test_it_validates_message_format(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['content' => 'Missing role'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_it_returns_expected_response_structure(): void
    {
        HisabiAgent::fake(['Here is your spending summary.']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Show me my spending summary'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'role',
                'content',
                'conversation_id',
                'charts',
                'components',
                'suggestions',
                'available_credits',
            ]);

        $this->assertEquals('assistant', $response->json('role'));
        $this->assertEquals('Here is your spending summary.', $response->json('content'));
        $this->assertNotNull($response->json('conversation_id'));
        $this->assertSame(4, $response->json('available_credits'));

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $response->json('conversation_id'),
            'role' => 'user',
            'content' => 'Show me my spending summary',
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $response->json('conversation_id'),
            'role' => 'assistant',
            'content' => 'Here is your spending summary.',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_handles_ai_chat_requests_when_the_accounts_table_uses_the_legacy_schema(): void
    {
        Account::forgetCachedTypeColumnSupport();

        Schema::shouldReceive('hasColumn')
            ->with('accounts', 'type')
            ->andReturn(false);

        HisabiAgent::fake(['Here is your spending summary.']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Show me my spending summary'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('content', 'Here is your spending summary.')
            ->assertJsonPath('available_credits', 4);

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 2);

        Account::forgetCachedTypeColumnSupport();
    }

    public function test_it_passes_user_prompt_to_agent(): void
    {
        HisabiAgent::fake(['Response text']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'What are my top expenses?'],
                ],
            ]);

        HisabiAgent::assertPrompted(
            fn($prompt) => str_contains($prompt->prompt, 'What are my top expenses?')
        );
    }

    public function test_it_continues_an_existing_conversation(): void
    {
        HisabiAgent::fake([
            'Food Expenses',
            'Here is your spending...',
            'Follow up response',
        ]);

        $firstResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Show me my spending'],
                ],
            ]);

        $conversationId = $firstResponse->json('conversation_id');

        $secondResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'conversation_id' => $conversationId,
                'messages' => [
                    ['role' => 'user', 'content' => 'Show me my spending'],
                    ['role' => 'assistant', 'content' => 'Here is your spending...'],
                    ['role' => 'user', 'content' => 'Tell me more about food expenses'],
                ],
            ]);

        $secondResponse->assertStatus(200);
        $this->assertSame($conversationId, $secondResponse->json('conversation_id'));

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 4);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Tell me more about food expenses',
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => 'Follow up response',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_returns_suggestions(): void
    {
        HisabiAgent::fake(['Some response']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertIsArray($response->json('suggestions'));
        $this->assertNotEmpty($response->json('suggestions'));
    }

    public function test_it_rejects_a_conversation_id_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $conversationId = (string) str()->uuid();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $otherUser->id,
            'title' => 'Other user conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'conversation_id' => $conversationId,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conversation_id']);
    }

    public function test_it_deducts_one_credit_for_each_prompt(): void
    {
        HisabiAgent::fake(['Credit deduction works.']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Summarize my spending'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('available_credits', 4);

        $this->assertSame(4, $this->user->fresh()->available_credits);
    }

    public function test_it_returns_payment_required_when_user_has_no_credits(): void
    {
        $this->user->forceFill([
            'available_credits' => 0,
        ])->save();

        HisabiAgent::fake(['This should not be used.']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Can you help me?'],
                ],
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('message', 'No available credits remaining.');

        HisabiAgent::assertNeverPrompted();
    }

    public function test_super_users_can_chat_without_credit_tracking(): void
    {
        /** @var User $superUser */
        $superUser = User::factory()->create([
            'available_credits' => 0,
            'is_super' => true,
        ]);

        HisabiAgent::fake(['Super user access works.']);

        $response = $this->actingAs($superUser)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Give me a quick summary'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('content', 'Super user access works.')
            ->assertJsonPath('available_credits', 0);

        $this->assertSame(0, $superUser->fresh()->available_credits);
    }

    public function test_it_pauses_for_structured_user_input_and_commits_the_initial_credit_charge(): void
    {
        $questions = [
            [
                'id' => 'account_id',
                'label' => 'Which account should I use?',
                'type' => 'select',
                'options' => [
                    ['label' => 'Checking', 'value' => 'checking'],
                    ['label' => 'Cash', 'value' => 'cash'],
                ],
            ],
            [
                'id' => 'note',
                'label' => 'What note should I save?',
                'type' => 'text',
            ],
        ];

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(ChatCommand::class))
            ->andThrow(new PendingUserInputToolCall($questions));

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Create a transaction for lunch'],
                ],
            ]);

        $conversationId = $response->json('conversation_id');

        $response->assertOk()
            ->assertJsonPath('status', 'requires_input')
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('interaction.tool_name', 'ask_user_for_input')
            ->assertJsonPath('interaction.questions.0.id', 'account_id')
            ->assertJsonPath('available_credits', 4);

        $this->assertSame(4, $this->user->fresh()->available_credits);
        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversationId,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Create a transaction for lunch',
            'user_id' => $this->user->id,
        ]);

        $assistantMessage = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->first([
                'content',
                'tool_calls',
                'meta',
            ]);

        $this->assertNotNull($assistantMessage);
        $this->assertSame('Please provide the requested details to continue.', $assistantMessage->content);

        $toolCalls = json_decode($assistantMessage->tool_calls, true);
        $meta = json_decode($assistantMessage->meta, true);

        $this->assertSame('ask_user_for_input', $toolCalls[0]['name']);
        $this->assertSame('account_id', $toolCalls[0]['arguments']['questions'][0]['id']);
        $this->assertStringStartsWith('fc_', $toolCalls[0]['id']);
        $this->assertStringStartsWith('call_', $toolCalls[0]['result_id']);
        $this->assertSame('pending', $meta['interaction']['status']);
        $this->assertStringStartsWith('fc_', $meta['interaction']['tool_call_id']);
        $this->assertStringStartsWith('call_', $meta['interaction']['tool_call_result_id']);
    }

    public function test_it_resumes_a_pending_tool_response_without_deducting_an_additional_credit(): void
    {
        $conversationId = $this->seedPendingToolResponseConversation([
            [
                'id' => 'account_id',
                'label' => 'Which account should I use?',
                'type' => 'select',
                'options' => [
                    ['label' => 'Checking', 'value' => 'checking'],
                    ['label' => 'Cash', 'value' => 'cash'],
                ],
            ],
            [
                'id' => 'note',
                'label' => 'What note should I save?',
                'type' => 'text',
            ],
        ]);

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldReceive('resumeAfterToolResponse')
            ->once()
            ->with($conversationId)
            ->andReturn(new ChatCommandResponse([
                'status' => 'completed',
                'role' => 'assistant',
                'content' => 'Thanks, I have enough information to continue.',
                'conversation_id' => $conversationId,
                'charts' => [],
                'components' => [],
                'suggestions' => [],
                'interaction' => null,
            ]));

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/chat/{$conversationId}/tool-response", [
                'answers' => [
                    'account_id' => 'checking',
                    'note' => 'Lunch with the team',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('content', 'Thanks, I have enough information to continue.')
            ->assertJsonPath('conversation_id', $conversationId)
            ->assertJsonPath('available_credits', 5);

        $assistantMessage = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->first([
                'tool_results',
                'meta',
            ]);

        $toolResults = json_decode($assistantMessage->tool_results, true);
        $meta = json_decode($assistantMessage->meta, true);

        $this->assertSame([
            'answers' => [
                'account_id' => 'checking',
                'note' => 'Lunch with the team',
            ],
        ], $toolResults[0]['result']);
        $this->assertSame('completed', $meta['interaction']['status']);
        $this->assertSame(5, $this->user->fresh()->available_credits);
    }

    public function test_it_validates_tool_response_answers_against_the_pending_questions(): void
    {
        $conversationId = $this->seedPendingToolResponseConversation([
            [
                'id' => 'account_id',
                'label' => 'Which account should I use?',
                'type' => 'select',
                'options' => [
                    ['label' => 'Checking', 'value' => 'checking'],
                ],
            ],
        ]);

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldNotReceive('resumeAfterToolResponse');

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/chat/{$conversationId}/tool-response", [
                'answers' => [
                    'account_id' => 'cash',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers.account_id']);

        $assistantMessage = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->first([
                'tool_results',
                'meta',
            ]);

        $meta = json_decode($assistantMessage->meta, true);

        $this->assertSame([], json_decode($assistantMessage->tool_results, true));
        $this->assertSame('pending', $meta['interaction']['status']);
        $this->assertSame(5, $this->user->fresh()->available_credits);
    }

    public function test_it_rejects_matching_source_and_destination_account_answers_before_resuming_the_ai(): void
    {
        $conversationId = $this->seedPendingToolResponseConversation([
            [
                'id' => 'from_account_id',
                'label' => 'Which account should fund this transaction?',
                'type' => 'select',
                'options' => [
                    ['label' => 'Checking', 'value' => 'checking'],
                    ['label' => 'Cash', 'value' => 'cash'],
                ],
            ],
            [
                'id' => 'to_account_id',
                'label' => 'Which account should receive this transaction?',
                'type' => 'select',
                'options' => [
                    ['label' => 'Checking', 'value' => 'checking'],
                    ['label' => 'Savings', 'value' => 'savings'],
                ],
            ],
        ]);

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldNotReceive('resumeAfterToolResponse');

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/chat/{$conversationId}/tool-response", [
                'answers' => [
                    'from_account_id' => 'checking',
                    'to_account_id' => 'checking',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers.to_account_id']);

        $assistantMessage = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->first([
                'tool_results',
                'meta',
            ]);

        $meta = json_decode($assistantMessage->meta, true);

        $this->assertSame([], json_decode($assistantMessage->tool_results, true));
        $this->assertSame('pending', $meta['interaction']['status']);
        $this->assertSame(5, $this->user->fresh()->available_credits);
    }

    public function test_it_rolls_back_changes_and_preserves_credits_when_provider_is_rate_limited(): void
    {
        $conversationId = (string) str()->uuid();

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(ChatCommand::class))
            ->andReturnUsing(function () use ($conversationId) {
                DB::table('agent_conversations')->insert([
                    'id' => $conversationId,
                    'user_id' => $this->user->id,
                    'title' => 'Should be rolled back',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                throw RateLimitedException::forProvider('zai');
            });

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Create a few things for me'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('content', 'The AI provider is temporarily rate limited. No changes were saved. Please try again in a moment.')
            ->assertJsonPath('available_credits', 5);

        $this->assertSame(5, $this->user->fresh()->available_credits);
        $this->assertDatabaseMissing('agent_conversations', [
            'id' => $conversationId,
        ]);
    }

    public function test_it_rolls_back_changes_and_returns_a_generic_error_message_for_unexpected_failures(): void
    {
        $conversationId = (string) str()->uuid();

        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(ChatCommand::class))
            ->andReturnUsing(function () use ($conversationId) {
                DB::table('agent_conversations')->insert([
                    'id' => $conversationId,
                    'user_id' => $this->user->id,
                    'title' => 'Should be rolled back',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                throw new \RuntimeException('Boom');
            });

        $this->app->instance(ChatCommandHandler::class, $handler);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Do something risky'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('content', 'I apologize, but I encountered an error processing your request. No changes were saved. Please try again in a moment.')
            ->assertJsonPath('available_credits', 5);

        $this->assertSame(5, $this->user->fresh()->available_credits);
        $this->assertDatabaseMissing('agent_conversations', [
            'id' => $conversationId,
        ]);
    }

    public function test_it_returns_a_generic_error_message_even_when_logging_the_failure_throws(): void
    {
        $handler = Mockery::mock(ChatCommandHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(ChatCommand::class))
            ->andThrow(new \RuntimeException('Boom'));

        $this->app->instance(ChatCommandHandler::class, $handler);

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'error'
                    && $message === 'Hisabi AI Chat Error: Boom'
                    && ($context['user_id'] ?? null) === $this->user->id;
            })
            ->andThrow(new \RuntimeException('Unable to write logs'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/chat', [
                'messages' => [
                    ['role' => 'user', 'content' => 'Do something risky'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('content', 'I apologize, but I encountered an error processing your request. No changes were saved. Please try again in a moment.')
            ->assertJsonPath('available_credits', 5);

        $this->assertSame(5, $this->user->fresh()->available_credits);
    }

    private function seedPendingToolResponseConversation(array $questions): string
    {
        $conversationId = (string) str()->uuid();
        $toolCallId = 'fc_' . bin2hex(random_bytes(25));
        $toolCallResultId = 'call_' . str()->random(24);

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $this->user->id,
            'title' => 'Pending interactive conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) str()->uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => HisabiAgent::class,
            'role' => 'assistant',
            'content' => 'Please provide the requested details to continue.',
            'attachments' => '[]',
            'tool_calls' => json_encode([[
                'id' => $toolCallId,
                'result_id' => $toolCallResultId,
                'name' => 'ask_user_for_input',
                'arguments' => [
                    'questions' => $questions,
                ],
            ]]),
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => json_encode([
                'interaction' => [
                    'status' => 'pending',
                    'tool_name' => 'ask_user_for_input',
                    'tool_call_id' => $toolCallId,
                    'tool_call_result_id' => $toolCallResultId,
                    'questions' => $questions,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }
}
