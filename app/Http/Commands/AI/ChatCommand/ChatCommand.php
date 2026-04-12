<?php

namespace App\Http\Commands\AI\ChatCommand;

class ChatCommand
{
    public array $messages;

    public ?string $conversationId;

    public function __construct(
        array $messages,
        ?string $conversationId = null
    ) {
        $this->messages = $messages;
        $this->conversationId = $conversationId;
    }
}
