<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TranscribeAudioRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TranscriptionController extends Controller
{
    public function token(): JsonResponse
    {
        $apiKey = config('ai.providers.eleven.key');
        $baseUrl = rtrim((string) config('ai.providers.eleven.url', 'https://api.elevenlabs.io/v1'), '/');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Speech-to-text is not configured.'], 503);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->post($baseUrl . '/single-use-token/realtime_scribe');

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to generate transcription token.'], 502);
        }

        return response()->json([
            'token' => $response->json('token'),
        ]);
    }

    public function transcribe(TranscribeAudioRequest $request): JsonResponse
    {
        $apiKey = config('ai.providers.eleven.key');
        $baseUrl = rtrim((string) config('ai.providers.eleven.url', 'https://api.elevenlabs.io/v1'), '/');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Transcription is not configured.'], 503);
        }

        $audio = $request->file('audio');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->send('POST', $baseUrl . '/speech-to-text', [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($audio->getRealPath(), 'r'),
                    'filename' => $audio->getClientOriginalName(),
                ],
                [
                    'name' => 'model_id',
                    'contents' => 'scribe_v2',
                ],
            ],
        ]);

        if (! $response->successful()) {
            return response()->json(['error' => 'Failed to transcribe audio.'], 502);
        }

        return response()->json([
            'text' => $response->json('text') ?? $response->json('transcription'),
            'language' => $response->json('language_code') ?? $response->json('language'),
            'duration' => $response->json('audio_duration_secs') ?? $response->json('duration'),
        ]);
    }
}
