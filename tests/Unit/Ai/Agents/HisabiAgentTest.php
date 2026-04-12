<?php

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\HisabiAgent;
use App\Ai\Tools\CreateTransactionTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HisabiAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_create_transaction_tool(): void
    {
        $agent = new HisabiAgent([], null);

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(CreateTransactionTool::class, $tools[0]);
    }

    public function test_instructions_include_financial_context(): void
    {
        $user = User::factory()->create();
        $agent = new HisabiAgent([], $user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('HisabiAI', $instructions);
        $this->assertStringContainsString('Financial Overview', $instructions);
        $this->assertStringContainsString('create_transaction', $instructions);
    }

    public function test_instructions_use_user_currency(): void
    {
        $user = User::factory()->create(['default_currency' => 'USD']);
        $agent = new HisabiAgent([], $user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('USD', $instructions);
    }

    public function test_instructions_fallback_to_system_currency(): void
    {
        $user = User::factory()->create(['default_currency' => null]);
        $agent = new HisabiAgent([], $user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString(config('hisabi.currency'), $instructions);
    }

    public function test_messages_converts_array_format(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $agent = new HisabiAgent($messages, null);
        $result = iterator_to_array($agent->messages());

        $this->assertCount(2, $result);
        $this->assertEquals('Hello', $result[0]->content);
        $this->assertEquals('Hi there!', $result[1]->content);
    }

    public function test_agent_can_be_faked(): void
    {
        HisabiAgent::fake(['Mocked response']);

        $user = User::factory()->create();
        $agent = new HisabiAgent([], $user);
        $response = $agent->prompt('What are my expenses?');

        $this->assertEquals('Mocked response', $response->text);

        HisabiAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'expenses'));
    }

    public function test_agent_uses_zai_chat_completions_endpoint(): void
    {
        Http::fake([
            'https://api.z.ai/api/paas/v4/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'model' => 'glm-5.1',
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello from ZAI',
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 3,
                    'total_tokens' => 13,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = (new HisabiAgent([], $user))->prompt('Say hello');

        $this->assertSame('Hello from ZAI', $response->text);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.z.ai/api/paas/v4/chat/completions'
                && $request['model'] === 'glm-5.1';
        });
    }
}
