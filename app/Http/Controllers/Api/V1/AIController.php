<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Commands\AI\ChatCommand\ChatCommand;
use App\Http\Commands\AI\ChatCommand\ChatCommandHandler;
use App\Http\Requests\Api\V1\AIChatRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class AIController extends Controller
{
    private ChatCommandHandler $chatCommandHandler;

    public function __construct(ChatCommandHandler $chatCommandHandler)
    {
        $this->chatCommandHandler = $chatCommandHandler;
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

                $tracksCredits = ! $user->isSuperUser();

                if ($tracksCredits && $user->available_credits < 1) {
                    return response()->json([
                        'message' => 'No available credits remaining.',
                        'available_credits' => $user->available_credits,
                    ], 402);
                }

                if ($tracksCredits) {
                    $user->available_credits -= 1;
                    $user->save();
                }

                $command = new ChatCommand(
                    $validated['messages'],
                    $validated['conversation_id'] ?? null,
                );

                $response = $this->chatCommandHandler->handle($command)->toResponse();
                $payload = $response->getData(true);
                $payload['available_credits'] = $user->available_credits;

                return response()->json($payload, $response->getStatusCode());
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RateLimitedException $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'warning',
                'Hisabi AI Chat Rate Limited',
                $exception,
                'The AI provider is temporarily rate limited. No changes were saved. Please try again in a moment.',
            );
        } catch (Throwable $exception) {
            return $this->assistantFailureResponse(
                $authenticatedUser,
                'error',
                'Hisabi AI Chat Error',
                $exception,
                'I apologize, but I encountered an error processing your request. No changes were saved. Please try again in a moment.',
            );
        }
    }

    private function assistantFailureResponse(
        User $user,
        string $logLevel,
        string $logMessage,
        Throwable $exception,
        string $content,
    ): JsonResponse {
        $this->safeLog($logLevel, $logMessage, $user, $exception);

        return $this->assistantErrorResponse($user, $content);
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
        } catch (Throwable) {
            // Avoid masking the original assistant failure with a logging failure.
        }
    }

    private function assistantErrorResponse(User $user, string $content): JsonResponse
    {
        return response()->json([
            'role' => 'assistant',
            'content' => $content,
            'conversation_id' => null,
            'charts' => [],
            'components' => [],
            'suggestions' => [
                'Can you show me my spending summary?',
                'What are my top expenses this month?',
            ],
            'available_credits' => $this->resolveAvailableCredits($user),
        ]);
    }

    private function resolveAvailableCredits(User $user): int
    {
        try {
            return (int) ($user->fresh()?->available_credits ?? $user->available_credits ?? 0);
        } catch (Throwable) {
            return (int) ($user->available_credits ?? 0);
        }
    }
}
