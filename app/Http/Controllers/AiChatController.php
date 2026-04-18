<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiChatIndexRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiChatController extends Controller
{
    public function index(AiChatIndexRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $conversationId = $request->validated('conversation_id');

        return Inertia::render('Ai/Index', [
            'conversations' => $this->recentConversations($user),
            'activeConversation' => $conversationId
                ? $this->activeConversation($conversationId, $user)
                : null,
        ]);
    }

    private function recentConversations(User $user): array
    {
        return DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get([
                'id',
                'title',
                'updated_at',
            ])
            ->map(fn(object $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updatedAt' => $conversation->updated_at,
            ])
            ->values()
            ->all();
    }

    private function activeConversation(string $conversationId, User $user): array
    {
        $conversation = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first([
                'id',
                'title',
                'updated_at',
            ]);

        abort_if($conversation === null, 404);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get([
                'id',
                'role',
                'content',
                'created_at',
            ])
            ->map(fn(object $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'createdAt' => $message->created_at,
            ])
            ->values()
            ->all();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'updatedAt' => $conversation->updated_at,
            'messages' => $messages,
        ];
    }
}
