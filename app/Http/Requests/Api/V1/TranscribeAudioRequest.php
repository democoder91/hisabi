<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                File::types(['mp3', 'mp4', 'm4a', 'wav', 'webm', 'aac', 'ogg'])->max(25 * 1024),
            ],
        ];
    }
}
