<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/webm,audio/aac,audio/ogg'],
        ];
    }
}
