<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Storage\DatabaseConversationStore;

class SanitizingDatabaseConversationStore extends DatabaseConversationStore
{
    /**
     * @return Collection<int, Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->flatMap(function ($record) {
                $toolCalls = collect(json_decode($record->tool_calls, true));
                $toolResults = collect(json_decode($record->tool_results, true));

                if ($record->role === 'user') {
                    return [new Message('user', $record->content)];
                }

                [$toolCalls, $toolResults] = $this->sanitizeToolPayloads($toolCalls, $toolResults);

                if ($toolCalls->isNotEmpty()) {
                    $messages = [];

                    $messages[] = new AssistantMessage(
                        $record->content ?: '',
                        $toolCalls->map(fn (array $toolCall) => new ToolCall(
                            id: $toolCall['id'],
                            name: $toolCall['name'],
                            arguments: $toolCall['arguments'],
                            resultId: $toolCall['result_id'] ?? null,
                        ))
                    );

                    if ($toolResults->isNotEmpty()) {
                        $messages[] = new ToolResultMessage(
                            $toolResults->map(fn (array $toolResult) => new ToolResult(
                                id: $toolResult['id'],
                                name: $toolResult['name'],
                                arguments: $toolResult['arguments'],
                                result: $toolResult['result'],
                                resultId: $toolResult['result_id'] ?? null,
                            ))
                        );
                    }

                    return $messages;
                }

                return [new AssistantMessage($record->content)];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $toolCalls
     * @param  Collection<int, array<string, mixed>>  $toolResults
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function sanitizeToolPayloads(Collection $toolCalls, Collection $toolResults): array
    {
        if ($toolCalls->isEmpty() || $toolResults->isEmpty()) {
            return [$toolCalls, $toolResults];
        }

        $resultKeys = $toolResults
            ->flatMap(function (mixed $toolResult): array {
                if (! is_array($toolResult)) {
                    return [];
                }

                return array_values(array_filter([
                    isset($toolResult['result_id']) ? (string) $toolResult['result_id'] : null,
                    isset($toolResult['id']) ? (string) $toolResult['id'] : null,
                ]));
            })
            ->unique()
            ->values();

        $sanitizedToolCalls = $toolCalls
            ->filter(function (mixed $toolCall) use ($resultKeys): bool {
                if (! is_array($toolCall)) {
                    return false;
                }

                $keys = array_values(array_filter([
                    isset($toolCall['result_id']) ? (string) $toolCall['result_id'] : null,
                    isset($toolCall['id']) ? (string) $toolCall['id'] : null,
                ]));

                return collect($keys)->contains(fn (string $key): bool => $resultKeys->contains($key));
            })
            ->values();

        if ($sanitizedToolCalls->isEmpty()) {
            return [collect(), collect()];
        }

        $toolCallKeys = $sanitizedToolCalls
            ->flatMap(fn (array $toolCall): array => array_values(array_filter([
                isset($toolCall['result_id']) ? (string) $toolCall['result_id'] : null,
                isset($toolCall['id']) ? (string) $toolCall['id'] : null,
            ])))
            ->unique()
            ->values();

        $sanitizedToolResults = $toolResults
            ->filter(function (mixed $toolResult) use ($toolCallKeys): bool {
                if (! is_array($toolResult)) {
                    return false;
                }

                $keys = array_values(array_filter([
                    isset($toolResult['result_id']) ? (string) $toolResult['result_id'] : null,
                    isset($toolResult['id']) ? (string) $toolResult['id'] : null,
                ]));

                return collect($keys)->contains(fn (string $key): bool => $toolCallKeys->contains($key));
            })
            ->values();

        return [$sanitizedToolCalls, $sanitizedToolResults];
    }
}