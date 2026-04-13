<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StartSubscriptionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => $this->route('plan'),
        ]);
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string'],
        ];
    }
}
