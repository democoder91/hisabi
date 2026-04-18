<?php

namespace App\Http\Commands\AI\ChatCommand;

use Illuminate\Http\JsonResponse;

readonly class ChatCommandResponse
{
    public function __construct(
        private array $response
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'status' => $this->response['status'] ?? 'completed',
            'role' => $this->response['role'],
            'content' => $this->response['content'],
            'conversation_id' => $this->response['conversation_id'] ?? null,
            'charts' => $this->response['charts'] ?? [],
            'components' => $this->response['components'] ?? [],
            'suggestions' => $this->response['suggestions'] ?? [],
            'interaction' => $this->response['interaction'] ?? null,
        ]);
    }
}
