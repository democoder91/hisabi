<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAiUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(12 * 1024),
            ],
            'purpose' => ['nullable', 'string', 'in:ai-chat,receipt,bill,general'],
            'custom_attributes' => ['nullable', 'array'],
        ];
    }
}
