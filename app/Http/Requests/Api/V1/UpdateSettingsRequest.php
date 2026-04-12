<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['sometimes', 'nullable', Rule::enum(Currency::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('default_currency')) {
            $this->merge([
                'default_currency' => strtoupper((string) $this->input('default_currency')),
            ]);
        }
    }
}