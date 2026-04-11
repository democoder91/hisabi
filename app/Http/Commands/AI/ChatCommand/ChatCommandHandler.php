<?php

namespace App\Http\Commands\AI\ChatCommand;

use App\Ai\Agents\HisabiAgent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatCommandHandler
{
    public function handle(ChatCommand $command): ChatCommandResponse
    {
        try {
            $user = Auth::user();

            // Pass all messages except the last one as conversation history
            // The last user message becomes the prompt
            $messages = $command->messages;
            $lastMessage = array_pop($messages);
            $prompt = $lastMessage['content'] ?? $lastMessage->content ?? '';

            $agent = new HisabiAgent($messages, $user);
            $response = $agent->prompt($prompt);

            return new ChatCommandResponse([
                'role' => 'assistant',
                'content' => $response->text,
                'charts' => [],
                'components' => [],
                'suggestions' => [
                    'Show me my spending summary for this month',
                    'What are my top expenses?',
                    'How much can I save this month?',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Hisabi AI Chat Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return new ChatCommandResponse([
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again in a moment.',
                'charts' => [],
                'components' => [],
                'suggestions' => [
                    'Can you show me my spending summary?',
                    'What are my top expenses this month?',
                ],
            ]);
        }
    }
}
