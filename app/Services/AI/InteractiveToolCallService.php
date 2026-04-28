<?php

namespace App\Services\AI;

use App\Ai\Agents\HisabiAgent;
use App\Ai\Exceptions\PendingUserInputToolCall;
use App\Domains\Account\Models\Account;
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

    private const ACCOUNT_QUESTION_IDS = [
        'account_id',
        'from_account_id',
        'to_account_id',
        'account_ids',
    ];

    private const QUESTION_ID_ALIASES = [
        'account' => 'account_id',
        'accounts' => 'account_ids',
        'budget' => 'budget_id',
        'destination_account' => 'to_account_id',
        'from_account' => 'from_account_id',
        'source_account' => 'from_account_id',
        'to_account' => 'to_account_id',
        'transaction' => 'transaction_id',
    ];

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

            $questionId = $this->canonicalizeQuestionId((string) ($question['id'] ?? ''));
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
            'result_id' => $interaction['tool_call_result_id'],
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

        $metaString = $this->encode(['interaction' => $interaction]);
        $refreshedInteraction = $this->pendingInteractionFromConversation($conversationId, $user, $metaString);

        return [
            'conversation_id' => $conversationId,
            'content' => $pendingToolCall->content,
            'interaction' => $refreshedInteraction ?? $interaction,
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

        $interaction = $this->pendingInteractionFromConversation($conversationId, $user, $pendingMessage->meta);

        if ($interaction === null) {
            throw ValidationException::withMessages([
                'answers' => 'This conversation is not waiting for a tool response.',
            ]);
        }

        $validatedAnswers = $this->validateAnswers($answers, $interaction['questions']);
        $meta = $this->decodeMeta($pendingMessage->meta);
        $meta['interaction']['questions'] = $interaction['questions'];
        $meta['interaction']['status'] = self::STATUS_COMPLETED;
        $meta['interaction']['answers'] = $validatedAnswers;
        $meta['interaction']['completed_at'] = now()->toIso8601String();

        DB::table('agent_conversation_messages')
            ->where('id', $pendingMessage->id)
            ->update([
                'tool_results' => $this->encode([[
                    'id' => $interaction['tool_call_id'],
                    'result_id' => $interaction['tool_call_result_id'] ?? $interaction['tool_call_id'],
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
            $questionId = $this->canonicalizeQuestionId((string) ($question['id'] ?? ''));
            $question['id'] = $questionId;

            if (! is_array($question['options'] ?? null)) {
                return $question;
            }

            if ($this->isAccountQuestionId($questionId)) {
                $question['options'] = $this->enrichAccountOptionMetadata($question['options']);
            }

            return $question;
        }, $questions);

        return [
            'status' => self::STATUS_PENDING,
            'tool_name' => (string) ($interaction['tool_name'] ?? self::TOOL_NAME),
            'tool_call_id' => $toolCallId,
            'tool_call_result_id' => (string) ($interaction['tool_call_result_id'] ?? $toolCallId),
            'questions' => $questions,
        ];
    }

    public function pendingInteractionFromConversation(string $conversationId, User $user, ?string $meta): ?array
    {
        $interaction = $this->pendingInteractionFromMeta($meta);

        if ($interaction === null) {
            return null;
        }

        $conversationAnswers = $this->conversationInteractionAnswers($conversationId, $user);

        $interaction['questions'] = array_map(
            fn(array $question): array => $this->refreshQuestionOptions($question, $conversationAnswers, $user),
            $interaction['questions'],
        );

        return $interaction;
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

        return $normalizedOptions;
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
        $answers = $this->normalizeAnswerKeys($answers, $questions);
        $errors = [];
        $normalizedAnswers = [];

        foreach ($answers as $answerKey => $value) {
            if (! collect($questions)->contains(fn(array $question): bool => ($question['id'] ?? null) === $answerKey)) {
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

                $answer = $this->normalizeSubmittedOptionValue((string) $rawAnswer, $question);

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

                $normalizedValue = $this->normalizeSubmittedOptionValue((string) $selectedValue, $question);

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

        $this->validateDistinctTransactionAccounts($normalizedAnswers, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedAnswers;
    }

    private function canonicalizeQuestionId(string $questionId): string
    {
        $normalizedQuestionId = trim($questionId);

        return self::QUESTION_ID_ALIASES[$normalizedQuestionId] ?? $normalizedQuestionId;
    }

    private function normalizeAnswerKeys(array $answers, array $questions): array
    {
        $availableQuestionIds = [];

        foreach ($questions as $question) {
            if (! is_array($question) || ! isset($question['id']) || ! is_string($question['id'])) {
                continue;
            }

            $availableQuestionIds[$question['id']] = true;
        }

        $normalizedAnswers = [];

        foreach ($answers as $answerKey => $value) {
            $key = (string) $answerKey;
            $canonicalKey = $this->canonicalizeQuestionId($key);

            if ($canonicalKey !== $key && isset($availableQuestionIds[$canonicalKey])) {
                if (! array_key_exists($canonicalKey, $normalizedAnswers)) {
                    $normalizedAnswers[$canonicalKey] = $value;
                }

                continue;
            }

            if (! array_key_exists($key, $normalizedAnswers)) {
                $normalizedAnswers[$key] = $value;
            }
        }

        return $normalizedAnswers;
    }

    private function conversationInteractionAnswers(string $conversationId, User $user): array
    {
        $answers = [];

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->orderBy('created_at')
            ->get(['meta']);

        foreach ($messages as $message) {
            $interaction = $this->decodeMeta($message->meta)['interaction'] ?? null;

            if (! is_array($interaction) || ($interaction['status'] ?? null) !== self::STATUS_COMPLETED) {
                continue;
            }

            $interactionAnswers = $interaction['answers'] ?? null;

            if (! is_array($interactionAnswers)) {
                continue;
            }

            foreach ($interactionAnswers as $key => $value) {
                $answers[$this->canonicalizeQuestionId((string) $key)] = $value;
            }
        }

        return $answers;
    }

    private function refreshQuestionOptions(array $question, array $conversationAnswers, User $user): array
    {
        $questionId = $question['id'] ?? null;

        if (! is_string($questionId) || ! is_array($question['options'] ?? null)) {
            return $question;
        }

        if ($this->isAccountQuestionId($questionId)) {
            $question['options'] = $this->refreshAccountOptions(
                $question['options'],
                $user,
                $this->excludedAccountIdForQuestion($questionId, $conversationAnswers),
            );
        }

        return $question;
    }

    private function excludedAccountIdForQuestion(string $questionId, array $conversationAnswers): ?int
    {
        if ($questionId === 'from_account_id') {
            $counterpartQuestionId = 'to_account_id';
        } elseif ($questionId === 'to_account_id') {
            $counterpartQuestionId = 'from_account_id';
        } else {
            $counterpartQuestionId = null;
        }

        if ($counterpartQuestionId === null) {
            return null;
        }

        $counterpartValue = $conversationAnswers[$counterpartQuestionId] ?? null;

        if ((is_string($counterpartValue) || is_numeric($counterpartValue)) && ctype_digit((string) $counterpartValue)) {
            return (int) $counterpartValue;
        }

        return null;
    }

    private function refreshAccountOptions(array $options, User $user, ?int $excludedAccountId): array
    {
        $currentOptions = $this->currentAccountOptions($user, $excludedAccountId);

        if ($currentOptions === []) {
            return $this->enrichAccountOptionMetadata($options);
        }

        $optionsByValue = [];
        $optionsByLabel = [];

        foreach ($currentOptions as $currentOption) {
            $optionsByValue[$currentOption['value']] = $currentOption;

            foreach ($currentOption['meta']['label_candidates'] ?? [] as $labelCandidate) {
                $normalizedLabel = $this->normalizeOptionLabel($labelCandidate);

                if ($normalizedLabel === '') {
                    continue;
                }

                $optionsByLabel[$normalizedLabel][] = $currentOption;
            }
        }

        $refreshedOptions = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = trim((string) ($option['label'] ?? $option['value'] ?? ''));
            $originalValue = trim((string) ($option['value'] ?? $label));
            $matchedOption = null;

            if ($originalValue !== '' && isset($optionsByValue[$originalValue])) {
                $matchedOption = $optionsByValue[$originalValue];
            } else {
                $matchingOptions = $optionsByLabel[$this->normalizeOptionLabel($label)] ?? [];

                if (count($matchingOptions) === 1) {
                    $matchedOption = $matchingOptions[0];
                }
            }

            if ($matchedOption === null) {
                continue;
            }

            $refreshedOption = $matchedOption;
            $refreshedOption['label'] = $label !== '' ? $label : $matchedOption['label'];

            if ($originalValue !== '' && $originalValue !== $matchedOption['value']) {
                $legacyValues = $refreshedOption['meta']['legacy_values'] ?? [];
                $legacyValues[] = $originalValue;
                $refreshedOption['meta']['legacy_values'] = array_values(array_unique(array_map('strval', $legacyValues)));
            }

            $refreshedOptions[$refreshedOption['value']] = $refreshedOption;
        }

        foreach ($currentOptions as $currentOption) {
            $refreshedOptions[$currentOption['value']] ??= $currentOption;
        }

        $result = array_values(array_map(function (array $option): array {
            unset($option['meta']['label_candidates']);

            return $option;
        }, $refreshedOptions));

        $result = array_values(array_filter($result, fn(array $o): bool => ($o['value'] ?? '') !== '__create_new__'));
        $result[] = $this->createNewAccountOption();

        return $result;
    }

    private function currentAccountOptions(User $user, ?int $excludedAccountId): array
    {
        $accounts = Account::query()
            ->accessibleTo($user)
            ->when($excludedAccountId !== null, fn($query) => $query->where('id', '!=', $excludedAccountId))
            ->orderByRaw(Account::localizedNameSqlExpression(app()->getLocale()) . ' ASC')
            ->orderBy('id')
            ->get($this->accountOptionColumns(includeName: true));

        return $accounts->map(function (Account $account): array {
            $translations = $account->getSafeNameTranslations();
            $labelCandidates = array_values(array_unique(array_filter(array_map(
                static fn(mixed $value): string => is_string($value) ? trim($value) : '',
                $translations,
            ))));
            $label = $account->getLocalizedName() ?? $labelCandidates[0] ?? ('Account #' . $account->id);

            return [
                'label' => $label,
                'value' => (string) $account->id,
                'meta' => [
                    'account_type' => (string) ($account->type ?? Account::TYPE_ASSET),
                    'owner_id' => (string) $account->user_id,
                    'label_candidates' => $labelCandidates,
                ],
            ];
        })->all();
    }

    private function enrichAccountOptionMetadata(array $options): array
    {
        $accountIds = array_values(array_filter(array_unique(array_map(
            static fn(array $option): ?int => is_string($option['value'] ?? null) && ctype_digit($option['value'])
                ? (int) $option['value']
                : null,
            $options,
        )), static fn(?int $accountId): bool => $accountId !== null));

        if ($accountIds === []) {
            return $options;
        }

        /** @var \Illuminate\Support\Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get($this->accountOptionColumns(includeName: false))
            ->keyBy('id');

        $enriched = array_map(static function (array $option) use ($accounts): array {
            $value = $option['value'] ?? null;

            if (! is_string($value) || ! ctype_digit($value)) {
                return $option;
            }

            $account = $accounts->get((int) $value);

            if (! $account instanceof Account) {
                return $option;
            }

            $meta = is_array($option['meta'] ?? null) ? $option['meta'] : [];
            $meta['account_type'] = (string) ($account->type ?? Account::TYPE_ASSET);
            $meta['owner_id'] = (string) $account->user_id;
            $option['meta'] = $meta;

            return $option;
        }, $options);

        $enriched = array_values(array_filter($enriched, fn(array $o): bool => ($o['value'] ?? '') !== '__create_new__'));
        $enriched[] = $this->createNewAccountOption();

        return $enriched;
    }

    private function createNewAccountOption(): array
    {
        return [
            'label' => '+ Create a new account',
            'value' => '__create_new__',
        ];
    }

    private function accountOptionColumns(bool $includeName): array
    {
        $columns = ['id'];

        if ($includeName) {
            $columns[] = 'name';
        }

        if (Account::supportsTypeColumn()) {
            $columns[] = 'type';
        }

        $columns[] = 'user_id';

        return $columns;
    }

    private function normalizeSubmittedOptionValue(string $value, array $question): string
    {
        $normalizedValue = trim($value);

        foreach ($question['options'] ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            $currentValue = trim((string) ($option['value'] ?? ''));

            if ($currentValue === '' || $currentValue === $normalizedValue) {
                continue;
            }

            $legacyValues = $option['meta']['legacy_values'] ?? [];

            if (! is_array($legacyValues)) {
                continue;
            }

            foreach ($legacyValues as $legacyValue) {
                if (trim((string) $legacyValue) === $normalizedValue) {
                    return $currentValue;
                }
            }
        }

        return $normalizedValue;
    }

    private function normalizeOptionLabel(string $label): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/', ' ', $label)));
    }

    private function isAccountQuestionId(string $questionId): bool
    {
        if (in_array($questionId, self::ACCOUNT_QUESTION_IDS, true)) {
            return true;
        }

        return Str::endsWith($questionId, ['_account_id', '_account_ids']);
    }

    private function validateDistinctTransactionAccounts(array $normalizedAnswers, array &$errors): void
    {
        $fromAccountId = $normalizedAnswers['from_account_id'] ?? null;
        $toAccountId = $normalizedAnswers['to_account_id'] ?? null;

        if (! is_string($fromAccountId) || ! is_string($toAccountId)) {
            return;
        }

        if (trim($fromAccountId) === '' || trim($toAccountId) === '') {
            return;
        }

        if (trim($fromAccountId) !== trim($toAccountId)) {
            return;
        }

        $errors['answers.to_account_id'] = ['Select a destination account that differs from the source account.'];
    }
}
