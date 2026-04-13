<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Commands\AI\ChatCommand\ChatCommand;
use App\Http\Commands\AI\ChatCommand\ChatCommandHandler;
use App\Http\Requests\Api\V1\AIChatRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
    }
}
