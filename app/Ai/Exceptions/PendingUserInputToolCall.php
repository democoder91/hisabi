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

    public string $toolCallResultId;

    public function __construct(array $questions, string $content = 'Please provide the requested details to continue.', ?string $toolCallId = null, ?string $toolCallResultId = null)
    {
        parent::__construct($content);

        $this->questions = $questions;
        $this->content = $content;
        $this->toolCallId = $toolCallId ?? ('fc_' . bin2hex(random_bytes(25)));
        $this->toolCallResultId = $toolCallResultId ?? ('call_' . Str::random(24));
    }

    public function interaction(): array
    {
        return [
            'status' => InteractiveToolCallService::STATUS_PENDING,
            'tool_name' => InteractiveToolCallService::TOOL_NAME,
            'tool_call_id' => $this->toolCallId,
            'tool_call_result_id' => $this->toolCallResultId,
            'questions' => $this->questions,
        ];
    }
}