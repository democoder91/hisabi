<?php

namespace App\Ai\Exceptions;

use App\Services\AI\InteractiveToolCallService;
use Illuminate\Support\Str;
use RuntimeException;

class PendingUserInputToolCall extends RuntimeException
{
    public array $questions;

    public string $content;

    public string $toolCallId;

    public function __construct(array $questions, string $content = 'Please provide the requested details to continue.', ?string $toolCallId = null)
    {
        parent::__construct($content);

        $this->questions = $questions;
        $this->content = $content;
        $this->toolCallId = $toolCallId ?? (string) Str::uuid7();
    }

    public function interaction(): array
    {
        return [
            'status' => InteractiveToolCallService::STATUS_PENDING,
            'tool_name' => InteractiveToolCallService::TOOL_NAME,
            'tool_call_id' => $this->toolCallId,
            'questions' => $this->questions,
        ];
    }
}