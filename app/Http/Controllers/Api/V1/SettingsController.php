<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\User\Services\UserService;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Models\User;
use App\Services\Currency\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private UserService $userService,
        private CurrencyRateService $currencyRateService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->payload($user));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $this->userService->updateProfile($user->id, $request->validated());

        return response()->json($this->payload($user));
    }

    private function payload(User $user): array
    {
        return [
            'settings' => [
                'default_currency' => $user->default_currency,
                'effective_currency' => $this->currencyRateService->effectiveCurrency($user),
                'locale' => $user->locale ?: config('app.locale'),
            ],
            'defaults' => [
                'currency' => config('hisabi.currency'),
                'locale' => config('app.locale'),
            ],
            'options' => [
                'currencies' => Currency::options(),
            ],
        ];
    }
}