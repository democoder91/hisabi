<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiChatIndexRequest;
use App\Models\AgentConversation;
use App\Models\User;
use App\Services\AI\AiChatUploadService;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiChatController extends Controller
{
    private InteractiveToolCallService $interactiveToolCallService;

    private AiChatUploadService $aiChatUploadService;

    public function __construct(
        InteractiveToolCallService $interactiveToolCallService,
        AiChatUploadService $aiChatUploadService
    ) {
        $this->interactiveToolCallService = $interactiveToolCallService;
        $this->aiChatUploadService = $aiChatUploadService;
    }

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
        $conversation = AgentConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->with(['messages' => function ($query) {
                $query->with('uploadedFiles')->orderBy('created_at');
            }])
            ->first(['id', 'title', 'updated_at']);

        abort_if($conversation === null, 404);

        $messages = $conversation->messages
            ->map(fn($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'uploads' => $this->aiChatUploadService->payloadsForMessage($message),
                'interaction' => $message->role === 'assistant'
                    ? $this->interactiveToolCallService->pendingInteractionFromConversation($conversationId, $user, $message->meta)
                    : null,
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
