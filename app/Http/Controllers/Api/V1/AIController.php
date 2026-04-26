<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Exceptions\PendingUserInputToolCall;
use App\Http\Controllers\Controller;
use App\Http\Commands\AI\ChatCommand\ChatCommand;
use App\Http\Commands\AI\ChatCommand\ChatCommandHandler;
use App\Http\Requests\Api\V1\AIChatRequest;
use App\Http\Requests\Api\V1\AIToolResponseRequest;
use App\Models\User;
use App\Services\AI\InteractiveToolCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class AIController extends Controller
{
    private ChatCommandHandler $chatCommandHandler;

    private InteractiveToolCallService $interactiveToolCallService;

    public function __construct(
        ChatCommandHandler $chatCommandHandler,
        InteractiveToolCallService $interactiveToolCallService
    ) {
        $this->chatCommandHandler = $chatCommandHandler;
        $this->interactiveToolCallService = $interactiveToolCallService;
    }

    public function chat(AIChatRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();

        try {
            return DB::transaction(function () use ($validated, $authenticatedUser): JsonResponse {
                $user = User::query()
                    ->lockForUpdate()
                    ->findOrFail($authenticatedUser->id);

                $creditResponse = $this->deductPromptCredit($user);

                if ($creditResponse !== null) {
                    return $creditResponse;
                }

                $command = new ChatCommand(
                    $validated['messages'],
                    $validated['conversation_id'] ?? null,
                );

                return $this->responseWithCredits(
                    $this->chatCommandHandler->handle($command)->toResponse(),
                    $user,
                );
            }, 3);
        } catch (PendingUserInputToolCall $exception) {
            return DB::transaction(function () use ($validated, $authenticatedUser, $exception): JsonResponse {
                $user = User::query()
                    ->lockForUpdate()
                    ->findOrFail($authenticatedUser->id);

                $creditResponse = $this->deductPromptCredit($user);

                if ($creditResponse !== null) {
                    return $creditResponse;
                }

                $pendingState = $this->interactiveToolCallService->storePendingConversationTurn(
                    $user,
                    $this->lastUserPrompt($validated['messages']),
                    $validated['conversation_id'] ?? null,
                    $exception,
                );

                return $this->pendingAssistantResponse($user, $pendingState['content'], $pendingState['conversation_id'], $pendingState['interaction']);
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RateLimitedException $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'warning',
                'Nexo AI Chat Rate Limited',
                $exception,
                'The AI provider is temporarily rate limited. No changes were saved. Please try again in a moment.',
                null,
            );
        } catch (Throwable $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'error',
                'Nexo AI Chat Error',
                $exception,
                'I apologize, but I encountered an error processing your request. No changes were saved. Please try again in a moment.',
                null,
            );
        }
    }

    public function toolResponse(AIToolResponseRequest $request, string $conversationId): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();

        try {
            return DB::transaction(function () use ($validated, $authenticatedUser, $conversationId): JsonResponse {
                $user = User::query()->findOrFail($authenticatedUser->id);

                $this->interactiveToolCallService->completePendingInteraction(
                    $conversationId,
                    $user,
                    $validated['answers'],
                );

                return $this->responseWithCredits(
                    $this->chatCommandHandler->resumeAfterToolResponse($conversationId)->toResponse(),
                    $user,
                );
            }, 3);
        } catch (PendingUserInputToolCall $exception) {
            return DB::transaction(function () use ($validated, $authenticatedUser, $conversationId, $exception): JsonResponse {
                $user = User::query()->findOrFail($authenticatedUser->id);

                $this->interactiveToolCallService->completePendingInteraction(
                    $conversationId,
                    $user,
                    $validated['answers'],
                );

                $pendingState = $this->interactiveToolCallService->appendPendingInteraction(
                    $conversationId,
                    $user,
                    $exception,
                );

                return $this->pendingAssistantResponse($user, $pendingState['content'], $conversationId, $pendingState['interaction']);
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RateLimitedException $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'warning',
                'Nexo AI Tool Response Rate Limited',
                $exception,
                'The AI provider is temporarily rate limited. Your answers were not lost. Please try again in a moment.',
                $conversationId,
            );
        } catch (Throwable $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'error',
                'Nexo AI Tool Response Error',
                $exception,
                'I apologize, but I encountered an error processing your answers. Please try again in a moment.',
                $conversationId,
            );
        }
    }

    private function assistantFailureResponse(
        User $user,
        string $logLevel,
        string $logMessage,
        Throwable $exception,
        string $content,
        ?string $conversationId
    ): JsonResponse {
        $this->safeLog($logLevel, $logMessage, $user, $exception);

        return $this->assistantErrorResponse($user, $content, $conversationId);
    }

    private function safeLog(string $logLevel, string $logMessage, User $user, Throwable $exception): void
    {
        try {
            $context = [
                'user_id' => $user->id,
            ];

            if ($logLevel === 'error') {
                $context['trace'] = $exception->getTraceAsString();
            }

            Log::log($logLevel, $logMessage . ': ' . $exception->getMessage(), $context);
        } catch (Throwable $ignored) {
            // Avoid masking the original assistant failure with a logging failure.
        }
    }

    private function assistantErrorResponse(User $user, string $content, ?string $conversationId = null): JsonResponse
    {
        return response()->json([
            'status' => 'failed',
            'role' => 'assistant',
            'content' => $content,
            'conversation_id' => $conversationId,
            'charts' => [],
            'components' => [],
            'suggestions' => [
                'Can you show me my spending summary?',
                'What are my top expenses this month?',
            ],
            'interaction' => null,
            'available_credits' => $this->resolveAvailableCredits($user),
        ]);
    }

    private function deductPromptCredit(User $user): ?JsonResponse
    {
        if ($user->isSuperUser()) {
            return null;
        }

        if ($user->available_credits < 1) {
            return response()->json([
                'message' => 'No available credits remaining.',
                'available_credits' => $user->available_credits,
            ], 402);
        }

        $user->available_credits -= 1;
        $user->save();

        return null;
    }

    private function lastUserPrompt(array $messages): string
    {
        $lastMessage = end($messages);

        return $lastMessage['content'] ?? $lastMessage->content ?? '';
    }

    private function pendingAssistantResponse(User $user, string $content, string $conversationId, array $interaction): JsonResponse
    {
        return response()->json([
            'status' => 'requires_input',
            'role' => 'assistant',
            'content' => $content,
            'conversation_id' => $conversationId,
            'charts' => [],
            'components' => [],
            'suggestions' => [],
            'interaction' => $interaction,
            'available_credits' => $this->resolveAvailableCredits($user),
        ]);
    }

    private function responseWithCredits(JsonResponse $response, User $user): JsonResponse
    {
        $payload = $response->getData(true);
        $payload['available_credits'] = $this->resolveAvailableCredits($user);

        return response()->json($payload, $response->getStatusCode());
    }

    private function resolveAvailableCredits(User $user): int
    {
        try {
            $freshUser = $user->fresh();

            return (int) (($freshUser ? $freshUser->available_credits : null) ?? $user->available_credits ?? 0);
        } catch (Throwable $ignored) {
            return (int) ($user->available_credits ?? 0);
        }
    }
}
