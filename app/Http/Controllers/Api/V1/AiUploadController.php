<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAiUploadRequest;
use App\Models\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiUploadController extends Controller
{
    public function store(StoreAiUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = 'local';
        $purpose = $request->validated('purpose', 'ai-chat');
        $extension = $file->getClientOriginalExtension();
        $directory = sprintf('private/ai-uploads/%s/%s', $request->user()->id, now()->format('Y/m'));
        $filename = Str::uuid7() . ($extension !== '' ? '.' . strtolower($extension) : '');
        $path = $file->storeAs($directory, $filename, $disk);

        $uploadedFile = UploadedFile::query()->create([
            'user_id' => $request->user()->id,
            'purpose' => $purpose,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'extension' => $extension !== '' ? strtolower($extension) : null,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'visibility' => 'private',
            'custom_attributes' => $request->validated('custom_attributes', []),
        ]);

        return response()->json([
            'upload' => $uploadedFile->toChatPayload(),
        ], 201);
    }

    public function show(Request $request, UploadedFile $uploadedFile): StreamedResponse
    {
        abort_unless((int) $uploadedFile->user_id === (int) $request->user()->id, 404);
        abort_unless(Storage::disk($uploadedFile->disk)->exists($uploadedFile->path), 404);

        $stream = Storage::disk($uploadedFile->disk)->readStream($uploadedFile->path);
        abort_unless($stream !== false, 404);

        $download = $request->boolean('download');
        $disposition = $download ? 'attachment' : 'inline';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $uploadedFile->mime_type,
            'Content-Length' => (string) $uploadedFile->size_bytes,
            'Content-Disposition' => sprintf("%s; filename=\"%s\"", $disposition, addslashes($uploadedFile->original_name)),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
