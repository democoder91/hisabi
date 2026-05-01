<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class UploadedFile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'custom_attributes' => 'array',
        'size_bytes' => 'integer',
    ];

    protected $appends = [
        'file_type_family',
        'is_previewable',
        'preview_url',
        'download_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachTo(Model $model): void
    {
        $this->attachable()->associate($model);
        $this->save();
    }

    public function toChatPayload(): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'file_type_family' => $this->file_type_family,
            'is_previewable' => $this->is_previewable,
            'preview_url' => $this->preview_url,
            'download_url' => $this->download_url,
            'custom_attributes' => $this->custom_attributes ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }

    public function deleteStoredFile(): void
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }

    protected function fileTypeFamily(): Attribute
    {
        return Attribute::get(function (): string {
            if (str_starts_with($this->mime_type, 'image/')) {
                return 'image';
            }

            if ($this->mime_type === 'application/pdf') {
                return 'pdf';
            }

            return 'file';
        });
    }

    protected function isPreviewable(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->file_type_family, ['image', 'pdf'], true));
    }

    protected function previewUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('api.ai.uploads.show', ['uploadedFile' => $this->id]));
    }

    protected function downloadUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('api.ai.uploads.show', ['uploadedFile' => $this->id, 'download' => 1]));
    }
}
