<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ToolUsageIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperUser();
    }

    public function rules(): array
    {
        return [
            'user' => 'nullable|string|max:255',
            'tool' => 'nullable|string|max:255',
            'conversation_id' => 'nullable|string|max:36',
            'per_page' => 'nullable|integer|min:10|max:100',
        ];
    }
}
