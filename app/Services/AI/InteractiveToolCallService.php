<?php

namespace App\Services\AI;

use App\Ai\Agents\HisabiAgent;
use App\Ai\Exceptions\PendingUserInputToolCall;
use App\Domains\Category\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InteractiveToolCallService
{
    public const TOOL_NAME = 'ask_user_for_input';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public function normalizeQuestions(array $questions): array
    {
        if ($questions === []) {
            throw new InvalidArgumentException('At least one question is required.');
        }

        $normalizedQuestions = [];
        $questionIds = [];

        foreach ($questions as $index => $question) {
            if (! is_array($question)) {
                throw new InvalidArgumentException(sprintf('Question %d must be an object.', $index + 1));
            }

            $questionId = trim((string) ($question['id'] ?? ''));
            $label = trim((string) ($question['label'] ?? ''));
            $type = trim((string) ($question['type'] ?? ''));

            if ($questionId === '') {
                throw new InvalidArgumentException(sprintf('Question %d is missing an id.', $index + 1));
            }

            if ($label === '') {
                throw new InvalidArgumentException(sprintf('Question "%s" is missing a label.', $questionId));
            }

            if (! in_array($type, ['text', 'select', 'multiselect'], true)) {
                throw new InvalidArgumentException(sprintf('Question "%s" has an invalid type.', $questionId));
            }

            if (in_array($questionId, $questionIds, true)) {
                throw new InvalidArgumentException(sprintf('Question ids must be unique. Duplicate id "%s" found.', $questionId));
            }

            $normalizedQuestion = [
                'id' => $questionId,
                'label' => $label,
                'type' => $type,
            ];

            if (in_array($type, ['select', 'multiselect'], true)) {
                $normalizedQuestion['options'] = $this->normalizeOptions($question['options'] ?? null, $questionId);
            }

            $normalizedQuestions[] = $normalizedQuestion;
            $questionIds[] = $questionId;
        }

        return $normalizedQuestions;
    }

    public function storePendingConversationTurn(
        User $user,
        string $prompt,
        ?string $conversationId,
        PendingUserInputToolCall $pendingToolCall
    ): array {
        $resolvedConversationId = $conversationId ?? $this->createConversation($user, $prompt);

        if ($conversationId !== null) {
            $this->touchConversation($conversationId);
        }

        $this->insertMessage([
            'conversation_id' => $resolvedConversationId,
            'user_id' => $user->id,
            'agent' => HisabiAgent::class,
            'role' => 'user',
            'content' => $prompt,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
        ]);

        return $this->appendPendingInteraction($resolvedConversationId, $user, $pendingToolCall);
    }

    public function appendPendingInteraction(
        string $conversationId,
        User $user,
        PendingUserInputToolCall $pendingToolCall
    ): array {
        $interaction = $pendingToolCall->interaction();

        $toolCalls = [[
            'id' => $interaction['tool_call_id'],
            'name' => self::TOOL_NAME,
            'arguments' => [
                'questions' => $interaction['questions'],
            ],
        ]];

        $this->insertMessage([
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => HisabiAgent::class,
            'role' => 'assistant',
            'content' => $pendingToolCall->content,
            'attachments' => '[]',
            'tool_calls' => $this->encode($toolCalls),
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => $this->encode([
                'interaction' => $interaction,
            ]),
        ]);

        $this->touchConversation($conversationId);

        return [
            'conversation_id' => $conversationId,
            'content' => $pendingToolCall->content,
            'interaction' => $interaction,
        ];
    }

    public function completePendingInteraction(string $conversationId, User $user, array $answers): array
    {
        $this->assertConversationOwnership($conversationId, $user);

        $pendingMessage = $this->findPendingInteractionMessage($conversationId, $user);

        if ($pendingMessage === null) {
            throw ValidationException::withMessages([
                'answers' => 'This conversation is not waiting for a tool response.',
            ]);
        }

        $interaction = $this->pendingInteractionFromMeta($pendingMessage->meta);

        if ($interaction === null) {
            throw ValidationException::withMessages([
                'answers' => 'This conversation is not waiting for a tool response.',
            ]);
        }

        $validatedAnswers = $this->validateAnswers($answers, $interaction['questions']);
        $meta = $this->decodeMeta($pendingMessage->meta);
        $meta['interaction']['status'] = self::STATUS_COMPLETED;
        $meta['interaction']['answers'] = $validatedAnswers;
        $meta['interaction']['completed_at'] = now()->toIso8601String();

        DB::table('agent_conversation_messages')
            ->where('id', $pendingMessage->id)
            ->update([
                'tool_results' => $this->encode([[
                    'id' => $interaction['tool_call_id'],
                    'name' => self::TOOL_NAME,
                    'arguments' => [
                        'questions' => $interaction['questions'],
                    ],
                    'result' => [
                        'answers' => $validatedAnswers,
                    ],
                ]]),
                'meta' => $this->encode($meta),
                'updated_at' => now(),
            ]);

        $this->touchConversation($conversationId);

        return [
            'assistant_message_id' => $pendingMessage->id,
            'interaction' => $interaction,
            'answers' => $validatedAnswers,
        ];
    }

    public function pendingInteractionFromMeta(?string $meta): ?array
    {
        $decoded = $this->decodeMeta($meta);
        $interaction = $decoded['interaction'] ?? null;

        if (! is_array($interaction)) {
            return null;
        }

        if (($interaction['status'] ?? null) !== self::STATUS_PENDING) {
            return null;
        }

        $toolCallId = trim((string) ($interaction['tool_call_id'] ?? ''));
        $questions = $interaction['questions'] ?? null;

        if ($toolCallId === '' || ! is_array($questions)) {
            return null;
        }

        $questions = array_map(function (array $question): array {
            if (($question['id'] ?? null) !== 'category_id' || ! is_array($question['options'] ?? null)) {
                return $question;
            }

            $question['options'] = $this->enrichCategoryOptionMetadata('category_id', $question['options']);

            return $question;
        }, $questions);

        return [
            'status' => self::STATUS_PENDING,
            'tool_name' => (string) ($interaction['tool_name'] ?? self::TOOL_NAME),
            'tool_call_id' => $toolCallId,
            'questions' => $questions,
        ];
    }

    private function assertConversationOwnership(string $conversationId, User $user): void
    {
        $conversationExists = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $conversationExists) {
            throw ValidationException::withMessages([
                'conversation_id' => 'The selected conversation is invalid.',
            ]);
        }
    }

    private function createConversation(User $user, string $prompt): string
    {
        $conversationId = (string) Str::uuid7();
        $normalizedPrompt = trim((string) preg_replace('/\s+/', ' ', $prompt));
        $title = $normalizedPrompt === ''
            ? 'New conversation'
            : Str::limit($normalizedPrompt, 52, '...');

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $user->id,
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }

    private function decodeMeta(?string $meta): array
    {
        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $value): string
    {
        return json_encode($value) ?: '[]';
    }

    private function findPendingInteractionMessage(string $conversationId, User $user): ?object
    {
        /** @var object|null $message */
        $message = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'meta',
            ])
            ->first(function (object $record): bool {
                return $this->pendingInteractionFromMeta($record->meta) !== null;
            });

        return $message;
    }

    private function insertMessage(array $attributes): void
    {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function normalizeOptions(mixed $options, string $questionId): array
    {
        if (! is_array($options) || $options === []) {
            throw new InvalidArgumentException(sprintf('Question "%s" requires one or more options.', $questionId));
        }

        $normalizedOptions = [];
        $optionValues = [];

        foreach ($options as $index => $option) {
            if (is_string($option) || is_numeric($option)) {
                $label = trim((string) $option);
                $value = $label;
                $meta = [];
            } elseif (is_array($option)) {
                $label = trim((string) ($option['label'] ?? $option['value'] ?? ''));
                $value = trim((string) ($option['value'] ?? $option['label'] ?? ''));
                $meta = is_array($option['meta'] ?? null) ? $option['meta'] : [];
            } else {
                throw new InvalidArgumentException(sprintf('Question "%s" has an invalid option at index %d.', $questionId, $index));
            }

            if ($label === '' || $value === '') {
                throw new InvalidArgumentException(sprintf('Question "%s" has an empty option at index %d.', $questionId, $index));
            }

            if (in_array($value, $optionValues, true)) {
                throw new InvalidArgumentException(sprintf('Question "%s" has duplicate option value "%s".', $questionId, $value));
            }

            $normalizedOption = [
                'label' => $label,
                'value' => $value,
            ];

            if ($meta !== []) {
                $normalizedOption['meta'] = $meta;
            }

            $normalizedOptions[] = $normalizedOption;
            $optionValues[] = $value;
        }

        return $this->enrichCategoryOptionMetadata($questionId, $normalizedOptions);
    }

    private function touchConversation(string $conversationId): void
    {
        DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->update([
                'updated_at' => now(),
            ]);
    }

    private function validateAnswers(array $answers, array $questions): array
    {
        $errors = [];
        $normalizedAnswers = [];
        $questionMap = [];

        foreach ($questions as $question) {
            $questionMap[$question['id']] = $question;
        }

        foreach ($answers as $answerKey => $value) {
            if (! array_key_exists($answerKey, $questionMap)) {
                $errors["answers.{$answerKey}"] = ['Unexpected answer key provided.'];
            }
        }

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $answerKey = "answers.{$questionId}";

            if (! array_key_exists($questionId, $answers)) {
                $errors[$answerKey] = ['This question requires an answer.'];
                continue;
            }

            $rawAnswer = $answers[$questionId];

            if ($question['type'] === 'text') {
                if (! is_string($rawAnswer) && ! is_numeric($rawAnswer)) {
                    $errors[$answerKey] = ['This answer must be a text value.'];
                    continue;
                }

                $answer = trim((string) $rawAnswer);

                if ($answer === '') {
                    $errors[$answerKey] = ['This answer cannot be empty.'];
                    continue;
                }

                $normalizedAnswers[$questionId] = $answer;

                continue;
            }

            $allowedValues = array_map(
                static fn(array $option): string => (string) $option['value'],
                $question['options'] ?? []
            );

            if ($question['type'] === 'select') {
                if (! is_string($rawAnswer) && ! is_numeric($rawAnswer)) {
                    $errors[$answerKey] = ['This answer must be a single option value.'];
                    continue;
                }

                $answer = trim((string) $rawAnswer);

                if (! in_array($answer, $allowedValues, true)) {
                    $errors[$answerKey] = ['This answer must match one of the provided options.'];
                    continue;
                }

                $normalizedAnswers[$questionId] = $answer;

                continue;
            }

            if (! is_array($rawAnswer)) {
                $errors[$answerKey] = ['This answer must be an array of option values.'];
                continue;
            }

            $selectedValues = [];

            foreach ($rawAnswer as $selectedValue) {
                if (! is_string($selectedValue) && ! is_numeric($selectedValue)) {
                    $errors[$answerKey] = ['Each selected value must be a string.'];
                    continue 2;
                }

                $normalizedValue = trim((string) $selectedValue);

                if (! in_array($normalizedValue, $allowedValues, true)) {
                    $errors[$answerKey] = ['Each selected value must match one of the provided options.'];
                    continue 2;
                }

                if (! in_array($normalizedValue, $selectedValues, true)) {
                    $selectedValues[] = $normalizedValue;
                }
            }

            if ($selectedValues === []) {
                $errors[$answerKey] = ['Select at least one option.'];
                continue;
            }

            $normalizedAnswers[$questionId] = $selectedValues;
        }

        $this->validateCategoryTypeConsistency($normalizedAnswers, $questionMap, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedAnswers;
    }

    private function enrichCategoryOptionMetadata(string $questionId, array $options): array
    {
        if ($questionId !== 'category_id' || $options === []) {
            return $options;
        }

        $categoryIds = array_values(array_unique(array_map(
            static fn(array $option): ?int => is_string($option['value'] ?? null) && ctype_digit($option['value'])
                ? (int) $option['value']
                : null,
            $options,
        )));

        $categoryIds = array_values(array_filter($categoryIds, static fn(?int $categoryId): bool => $categoryId !== null));

        if ($categoryIds === []) {
            return $options;
        }

        /** @var array<int, string> $categoryTypes */
        $categoryTypes = DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->pluck('type', 'id')
            ->all();

        return array_map(static function (array $option) use ($categoryTypes): array {
            $value = $option['value'] ?? null;

            if (! is_string($value) || ! ctype_digit($value)) {
                return $option;
            }

            $categoryType = $categoryTypes[(int) $value] ?? null;

            if (! is_string($categoryType) || trim($categoryType) === '') {
                return $option;
            }

            $meta = is_array($option['meta'] ?? null) ? $option['meta'] : [];
            $meta['category_type'] = $categoryType;
            $option['meta'] = $meta;

            return $option;
        }, $options);
    }

    private function validateCategoryTypeConsistency(array $normalizedAnswers, array $questionMap, array &$errors): void
    {
        if (! array_key_exists('category_id', $normalizedAnswers)) {
            return;
        }

        $expectedType = $normalizedAnswers['category_type'] ?? $normalizedAnswers['transaction_type'] ?? null;

        if (! is_string($expectedType)) {
            return;
        }

        $expectedType = strtoupper(trim($expectedType));

        if (! in_array($expectedType, [
            Category::EXPENSES,
            Category::INCOME,
            Category::SAVINGS,
            Category::INVESTMENT,
        ], true)) {
            return;
        }

        $categoryId = $normalizedAnswers['category_id'];

        if (! is_string($categoryId) || ! ctype_digit($categoryId)) {
            return;
        }

        $actualType = $this->resolveCategoryTypeFromQuestionOption($questionMap['category_id'] ?? null, $categoryId)
            ?? DB::table('categories')->where('id', (int) $categoryId)->value('type');

        if (! is_string($actualType) || strtoupper(trim($actualType)) === $expectedType) {
            return;
        }

        $errors['answers.category_id'] = ['Select a category that matches the chosen transaction type.'];
    }

    private function resolveCategoryTypeFromQuestionOption(?array $question, string $categoryId): ?string
    {
        if (! is_array($question)) {
            return null;
        }

        foreach ($question['options'] ?? [] as $option) {
            if (! is_array($option) || ($option['value'] ?? null) !== $categoryId) {
                continue;
            }

            $categoryType = $option['meta']['category_type'] ?? null;

            return is_string($categoryType) ? $categoryType : null;
        }

        return null;
    }
}