<?php

namespace App\Http\Commands\AI\ChatCommand;

use App\Ai\Agents\HisabiAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatCommandHandler
{
    public function handle(ChatCommand $command): ChatCommandResponse
    {
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
    }
}
