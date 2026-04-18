<?php

namespace App\Http\Commands\AI\ChatCommand;

use App\Ai\Agents\HisabiAgent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatCommandHandler
{
    private const RESUME_AFTER_TOOL_RESPONSE_PROMPT = 'Continue from the previously provided tool response and answer the user without repeating the same clarification questions.';

    public function handle(ChatCommand $command): ChatCommandResponse
    {
        [$agent] = $this->resolveAgent($command->conversationId);

        $response = $agent->prompt($this->extractPrompt($command->messages));

        return $this->buildResponse($response);
    }

    public function resumeAfterToolResponse(string $conversationId): ChatCommandResponse
    {
        [$agent, $user] = $this->resolveAgent($conversationId);

        $response = $agent->prompt(self::RESUME_AFTER_TOOL_RESPONSE_PROMPT);

        $this->deleteSyntheticResumePromptMessage($conversationId, $user);

        return $this->buildResponse($response);
    }

    private function buildResponse(object $response): ChatCommandResponse
    {
        return new ChatCommandResponse([
            'status' => 'completed',
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
            'interaction' => null,
        ]);
    }

    private function deleteSyntheticResumePromptMessage(string $conversationId, ?User $user): void
    {
        $userId = $user ? $user->id : null;

        $messageId = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', self::RESUME_AFTER_TOOL_RESPONSE_PROMPT)
            ->when($userId !== null, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->value('id');

        if ($messageId !== null) {
            DB::table('agent_conversation_messages')
                ->where('id', $messageId)
                ->delete();
        }
    }

    private function extractPrompt(array $messages): string
    {
        $lastMessage = array_pop($messages);

        return $lastMessage['content'] ?? $lastMessage->content ?? '';
    }

    private function resolveAgent(?string $conversationId): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $conversationUserId = $user ? $user->id : null;
        $agent = new HisabiAgent($user);

        if ($conversationId !== null) {
            $conversationExists = DB::table('agent_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $conversationUserId)
                ->exists();

            if (! $conversationExists) {
                throw ValidationException::withMessages([
                    'conversation_id' => 'The selected conversation is invalid.',
                ]);
            }

            $agent->continue($conversationId, $user);

            return [$agent, $user];
        }

        $agent->forUser($user);

        return [$agent, $user];
    }
}
