<?php

namespace Tests\Feature\Api\V1;

use App\Ai\Agents\HisabiAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
