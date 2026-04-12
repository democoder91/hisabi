<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domains\User\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Currency\UpdateCurrencyRatesRequest;
use App\Http\Requests\Api\V1\UpdateSettingsRequest;
use App\Models\User;
use App\Services\Currency\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencySettingsController extends Controller
{
    private CurrencyRateService $currencyRateService;

    private UserService $userService;

    public function __construct(CurrencyRateService $currencyRateService, UserService $userService)
    {
        $this->currencyRateService = $currencyRateService;
        $this->userService = $userService;
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->currencyRateService->currenciesPayload($user));
    }

    public function updatePreference(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $this->userService->updateProfile($user->id, $request->validated());

        return response()->json($this->currencyRateService->currenciesPayload($user));
    }

    public function updateRates(UpdateCurrencyRatesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->currencyRateService->updateManualRates($user, $request->validated('rates'));

        return response()->json($this->currencyRateService->currenciesPayload($user->fresh()));
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->currencyRateService->refreshRates($user);

        return response()->json($this->currencyRateService->currenciesPayload($user->fresh()));
    }
}