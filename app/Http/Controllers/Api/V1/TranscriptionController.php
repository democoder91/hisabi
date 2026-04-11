<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TranscriptionController extends Controller
{
    public function token(): JsonResponse
    {
        $apiKey = config('ai.providers.eleven.key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Speech-to-text is not configured.'], 503);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->post('https://api.elevenlabs.io/v1/single-use-token/realtime_scribe');

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to generate transcription token.'], 502);
        }

        return response()->json([
            'token' => $response->json('token'),
        ]);
    }
}
