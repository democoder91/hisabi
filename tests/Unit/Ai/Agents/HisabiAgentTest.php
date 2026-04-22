<?php

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\HisabiAgent;
use App\Ai\Tools\AskUserInputTool;
use App\Ai\Tools\CreateAccountTool;
use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\CreateTransferTool;
use App\Ai\Tools\CreateTransactionTool;
use App\Ai\Tools\EditAccountTool;
use App\Ai\Tools\EditBudgetTool;
use App\Ai\Tools\EditTransactionTool;
use App\Ai\Tools\ListAccountsTool;
use App\Ai\Tools\ListBudgetsTool;
use App\Ai\Tools\ListTransactionsTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HisabiAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_all_finance_management_tools(): void
    {
        $agent = new HisabiAgent();

        $tools = $agent->tools();

        $this->assertSame([
            CreateAccountTool::class,
            CreateTransactionTool::class,
            CreateTransferTool::class,
            CreateBudgetTool::class,
            EditAccountTool::class,
            EditTransactionTool::class,
            EditBudgetTool::class,
            ListAccountsTool::class,
            ListTransactionsTool::class,
            ListBudgetsTool::class,
            AskUserInputTool::class,
        ], array_map(static fn($tool) => get_class($tool), $tools));
    }

    public function test_instructions_include_financial_context(): void
    {
        $user = User::factory()->create();
        $agent = new HisabiAgent($user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('HisabiAI', $instructions);
        $this->assertStringContainsString("User's Financial Summary", $instructions);
        $this->assertStringContainsString('create_account', $instructions);
        $this->assertStringContainsString('create_transaction', $instructions);
        $this->assertStringContainsString('create_transfer', $instructions);
        $this->assertStringContainsString('edit_budget', $instructions);
        $this->assertStringContainsString('ask_user_for_input', $instructions);
        $this->assertStringContainsString('If the user references an account by name instead of ID', $instructions);
        $this->assertStringContainsString('If a write tool reports missing or invalid account details', $instructions);
        $this->assertStringContainsString('Do not invent transaction memos', $instructions);
        $this->assertStringContainsString('source-account to destination-account movements', $instructions);
        $this->assertStringContainsString('at least one account ID', $instructions);
        $this->assertStringContainsString('Explain how the Hisabi application works', $instructions);
        $this->assertStringNotContainsString('list_categories', $instructions);
    }

    public function test_instructions_use_user_currency(): void
    {
        $user = User::factory()->create(['default_currency' => 'USD']);
        $agent = new HisabiAgent($user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('USD', $instructions);
    }

    public function test_instructions_fallback_to_system_currency(): void
    {
        $user = User::factory()->create(['default_currency' => null]);
        $agent = new HisabiAgent($user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString(config('hisabi.currency'), $instructions);
    }

    public function test_agent_can_remember_conversations_for_a_user(): void
    {
        HisabiAgent::fake(['Hi there!']);

        $user = User::factory()->create();

        $response = (new HisabiAgent($user))
            ->forUser($user)
            ->prompt('Hello');

        $this->assertSame('Hi there!', $response->text);
        $this->assertNotNull($response->conversationId);
        $this->assertDatabaseHas('agent_conversations', [
            'id' => $response->conversationId,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
    }

    public function test_agent_can_be_faked(): void
    {
        HisabiAgent::fake(['Mocked response']);

        $user = User::factory()->create();
        $agent = new HisabiAgent($user);
        $response = $agent->prompt('What are my expenses?');

        $this->assertEquals('Mocked response', $response->text);

        HisabiAgent::assertPrompted(fn($prompt) => str_contains($prompt->prompt, 'expenses'));
    }

    public function test_agent_uses_openai_responses_endpoint(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp-test',
                'status' => 'completed',
                'model' => 'gpt-4o',
                'output' => [[
                    'id' => 'msg-test',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Hello from OpenAI',
                        'annotations' => [],
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 3,
                    'input_tokens_details' => ['cached_tokens' => 0],
                    'output_tokens_details' => ['reasoning_tokens' => 0],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = (new HisabiAgent($user))->prompt('Say hello');

        $this->assertSame('Hello from OpenAI', $response->text);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/responses';
        });
    }
}
