<?php

namespace App\Http\Commands\AI\ChatCommand;

use App\Ai\Agents\HisabiAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatCommandHandler
{
    public function handle(ChatCommand $command): ChatCommandResponse
    {
        try {
            $user = Auth::user();
            $conversationUserId = $user ? $user->id : null;

            $messages = $command->messages;
            $lastMessage = array_pop($messages);
            $prompt = $lastMessage['content'] ?? $lastMessage->content ?? '';

            $agent = new HisabiAgent($user);

            if ($command->conversationId) {
                $conversationExists = DB::table('agent_conversations')
                    ->where('id', $command->conversationId)
                    ->where('user_id', $conversationUserId)
                    ->exists();

                if (! $conversationExists) {
                    throw ValidationException::withMessages([
                        'conversation_id' => 'The selected conversation is invalid.',
                    ]);
                }

                $agent->continue($command->conversationId, $user);
            } else {
                $agent->forUser($user);
            }

            $response = $agent->prompt($prompt);

            return new ChatCommandResponse([
                'role' => 'assistant',
                'content' => $response->text,
                'conversation_id' => $response->conversationId,
                'charts' => [],
                'components' => [],
                'suggestions' => [
                    'Show me my spending summary for this month',
                    'What are my top expenses?',
                    'How much can I save this month?',
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Hisabi AI Chat Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return new ChatCommandResponse([
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again in a moment.',
                'conversation_id' => null,
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
