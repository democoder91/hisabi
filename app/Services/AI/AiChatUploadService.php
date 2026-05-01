<?php

namespace App\Services\AI;

use App\Models\AgentConversationMessage;
use App\Models\UploadedFile;
use App\Models\User;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

class AiChatUploadService
{
    public function requestedUploadIds(array $messages): array
    {
        $lastMessage = end($messages);
        $uploadIds = $lastMessage['upload_ids'] ?? $lastMessage->upload_ids ?? [];

        if (! is_array($uploadIds)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $uploadIds)));
    }

    public function attachUploadsToLatestUserMessage(User $user, ?string $conversationId, string $prompt, array $uploadIds): array
    {
        if ($conversationId === null || $uploadIds === []) {
            return [];
        }

        $message = AgentConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('role', 'user')
            ->where('content', $prompt)
            ->orderByDesc('created_at')
            ->first();

        if (! $message) {
            return [];
        }

        $uploads = UploadedFile::query()
            ->whereIn('id', $uploadIds)
            ->where('user_id', $user->id)
            ->whereNull('attachable_type')
            ->whereNull('attachable_id')
            ->orderBy('id')
            ->get();

        if ($uploads->isEmpty()) {
            return [];
        }

        foreach ($uploads as $upload) {
            $upload->attachTo($message);
        }

        $payload = $uploads->fresh()->map(fn (UploadedFile $upload): array => $upload->toChatPayload())->values()->all();

        $message->forceFill([
            'attachments' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        ])->save();

        return $payload;
    }

    public function promptAttachments(User $user, array $uploadIds): array
    {
        if ($uploadIds === []) {
            return [];
        }

        return UploadedFile::query()
            ->whereIn('id', $uploadIds)
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(function (UploadedFile $upload) {
                if (str_starts_with($upload->mime_type, 'image/')) {
                    return Image::fromStorage($upload->path, $upload->disk)->as($upload->original_name);
                }

                return Document::fromStorage($upload->path, $upload->disk)->as($upload->original_name);
            })
            ->all();
    }

    public function payloadsForMessage(AgentConversationMessage $message): array
    {
        $message->loadMissing('uploadedFiles');

        if ($message->uploadedFiles->isNotEmpty()) {
            return $message->uploadedFiles
                ->map(fn (UploadedFile $upload): array => $upload->toChatPayload())
                ->values()
                ->all();
        }

        $legacyAttachments = json_decode((string) $message->attachments, true);

        return is_array($legacyAttachments) ? array_values($legacyAttachments) : [];
    }
}
