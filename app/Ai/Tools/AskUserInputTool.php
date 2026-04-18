<?php

namespace App\Ai\Tools;

use App\Ai\Exceptions\PendingUserInputToolCall;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskUserInputTool implements Tool
{
    public function name(): string
    {
        return InteractiveToolCallService::TOOL_NAME;
    }

    public function description(): Stringable|string
    {
        return 'Use this tool to ask the user for specific missing information, such as selecting an account, category, or confirming details before executing a transaction.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $questions = app(InteractiveToolCallService::class)->normalizeQuestions(
                $request->array('questions')
            );
        } catch (InvalidArgumentException $exception) {
            return 'Unable to request structured user input: '.$exception->getMessage();
        }

        throw new PendingUserInputToolCall($questions);
    }

    public function schema(JsonSchema $schema): array
    {
        $optionSchema = $schema->object([
            'label' => $schema->string()->required()->description('Option label shown to the user.'),
            'value' => $schema->string()->required()->description('Option value returned in the tool response payload.'),
        ])->withoutAdditionalProperties();

        $questionSchema = $schema->object([
            'id' => $schema->string()->required()->description('Stable key used in the tool response payload.'),
            'label' => $schema->string()->required()->description('Question label shown to the user.'),
            'type' => $schema->string()->required()->enum(['text', 'select', 'multiselect']),
            'options' => $schema->array()
                ->items($optionSchema)
                ->description('Required for select and multiselect questions. Each option should include a label and value.'),
        ])->withoutAdditionalProperties();

        return [
            'questions' => $schema->array()
                ->items($questionSchema)
                ->min(1)
                ->required()
                ->description('One or more structured questions to present to the user.'),
        ];
    }
}