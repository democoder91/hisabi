<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StartCreditCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'package' => $this->route('package'),
        ]);
    }

    public function rules(): array
    {
        return [
            'package' => ['required', 'string'],
        ];
    }
}
