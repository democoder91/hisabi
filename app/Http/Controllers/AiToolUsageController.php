<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToolUsageIndexRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiToolUsageController extends Controller
{
    public function index(ToolUsageIndexRequest $request): Response
    {
        $validated = $request->validated();

        $userFilter = trim((string) ($validated['user'] ?? ''));
        $toolFilter = trim((string) ($validated['tool'] ?? ''));
        $conversationFilter = trim((string) ($validated['conversation_id'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $paginator = DB::table('agent_conversation_messages as messages')
            ->join('agent_conversations as conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->leftJoin('users', 'users.id', '=', 'conversations.user_id')
            ->select([
                'messages.id',
                'messages.conversation_id',
                'messages.agent',
                'messages.content',
                'messages.tool_calls',
                'messages.tool_results',
                'messages.created_at',
                'conversations.title as conversation_title',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->where('messages.role', 'assistant')
            ->where(function ($query) {
                $query->where('messages.tool_calls', '!=', '[]')
                    ->orWhere('messages.tool_results', '!=', '[]');
            })
            ->when($userFilter !== '', function ($query) use ($userFilter) {
                $query->where(function ($userQuery) use ($userFilter) {
                    $userQuery->where('users.name', 'like', "%{$userFilter}%")
                        ->orWhere('users.email', 'like', "%{$userFilter}%");
                });
            })
            ->when($toolFilter !== '', function ($query) use ($toolFilter) {
                $query->where(function ($toolQuery) use ($toolFilter) {
                    $toolQuery->where('messages.tool_calls', 'like', "%{$toolFilter}%")
                        ->orWhere('messages.tool_results', 'like', "%{$toolFilter}%");
                });
            })
            ->when($conversationFilter !== '', function ($query) use ($conversationFilter) {
                $query->where('messages.conversation_id', 'like', "%{$conversationFilter}%");
            })
            ->orderByDesc('messages.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Ai/ToolUsage', [
            'logs' => $this->transformLogs($paginator),
            'filters' => [
                'user' => $userFilter,
                'tool' => $toolFilter,
                'conversation_id' => $conversationFilter,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'hasMorePages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    private function transformLogs(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(function (object $message): array {
                $toolCalls = $this->decodeJsonArray((string) $message->tool_calls);
                $toolResults = $this->decodeJsonArray((string) $message->tool_results);

                return [
                    'id' => $message->id,
                    'conversationId' => $message->conversation_id,
                    'conversationTitle' => $message->conversation_title,
                    'agent' => $message->agent,
                    'content' => $message->content,
                    'toolCalls' => $toolCalls,
                    'toolResults' => $toolResults,
                    'toolNames' => $this->extractToolNames($toolCalls, $toolResults),
                    'user' => [
                        'id' => $message->user_id,
                        'name' => $message->user_name,
                        'email' => $message->user_email,
                    ],
                    'createdAt' => $message->created_at,
                ];
            })
            ->values()
            ->all();
    }

    private function decodeJsonArray(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractToolNames(array $toolCalls, array $toolResults): array
    {
        return Collection::make([$toolCalls, $toolResults])
            ->flatten(1)
            ->pluck('name')
            ->filter(fn(mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
    }
}
