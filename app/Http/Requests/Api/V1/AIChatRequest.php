<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'messages.*.upload_ids' => 'sometimes|array',
            'messages.*.upload_ids.*' => [
                'integer',
                Rule::exists('uploaded_files', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()?->id)
                        ->whereNull('attachable_type')
                        ->whereNull('attachable_id');
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $messages = $this->input('messages', []);
            $lastIndex = count($messages) - 1;

            foreach ($messages as $index => $message) {
                $uploadIds = $message['upload_ids'] ?? null;

                if (! is_array($uploadIds) || $uploadIds === []) {
                    continue;
                }

                if ($index !== $lastIndex) {
                    $validator->errors()->add("messages.{$index}.upload_ids", 'Only the latest message may include uploads.');
                }

                if (($message['role'] ?? null) !== 'user') {
                    $validator->errors()->add("messages.{$index}.upload_ids", 'Uploads may only be attached to user messages.');
                }
            }
        });
    }
}
