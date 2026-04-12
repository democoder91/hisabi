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

    public function transcribe(TranscribeAudioRequest $request): JsonResponse
    {
        $apiKey = config('ai.providers.openai.key');
        $baseUrl = rtrim((string) config('ai.providers.openai.url', 'https://api.openai.com/v1'), '/');

        if (empty($apiKey)) {
            return response()->json(['error' => 'Transcription is not configured.'], 503);
        }

        $audio = $request->file('audio');

        $response = Http::withToken($apiKey)->send('POST', $baseUrl . '/audio/transcriptions', [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($audio->getRealPath(), 'r'),
                    'filename' => $audio->getClientOriginalName(),
                ],
                [
                    'name' => 'model',
                    'contents' => 'whisper-1',
                ],
                [
                    'name' => 'response_format',
                    'contents' => 'verbose_json',
                ],
            ],
        ]);

        if (! $response->successful()) {
            return response()->json(['error' => 'Failed to transcribe audio.'], 502);
        }

        return response()->json([
            'text' => $response->json('text'),
            'language' => $response->json('language'),
            'duration' => $response->json('duration'),
        ]);
    }
}
