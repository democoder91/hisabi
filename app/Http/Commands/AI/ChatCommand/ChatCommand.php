<?php

namespace App\Http\Commands\AI\ChatCommand;

class ChatCommand
{
    public array $messages;

    public ?string $conversationId;

    public array $attachments;

    public function __construct(
        array $messages,
        ?string $conversationId = null,
        array $attachments = []
    ) {
        $this->messages = $messages;
        $this->conversationId = $conversationId;
        $this->attachments = $attachments;
    }
}
