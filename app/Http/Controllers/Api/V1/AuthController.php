<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginApiRequest;
use App\Http\Requests\Api\V1\RegisterApiRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterApiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $user->createToken($this->resolveDeviceName($request))->plainTextToken,
        ], 201);
    }

    public function login(LoginApiRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = User::query()->where('email', strtolower($request->string('email')->toString()))->firstOrFail();

        return response()->json([
            'user' => $this->serializeUser($user),
            'token' => $user->createToken($this->resolveDeviceName($request))->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $token = $user ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function resolveDeviceName(Request $request): string
    {
        return $request->string('device_name')->trim()->toString()
            ?: ($request->userAgent() ?: 'api-client');
    }

    private function serializeUser(?User $user): array
    {
        abort_unless($user, 401);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'default_currency' => $user->default_currency,
            'locale' => $user->locale,
        ];
    }
}
