<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AIChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => 'nullable|uuid',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string',
        ];
    }
}
