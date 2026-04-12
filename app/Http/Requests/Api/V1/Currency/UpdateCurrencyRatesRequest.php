<?php

namespace App\Http\Requests\Api\V1\Currency;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.currency' => ['required', Rule::enum(Currency::class)],
            'rates.*.rate' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rates = collect($this->input('rates', []))
            ->map(function (array $rate): array {
                if (isset($rate['currency'])) {
                    $rate['currency'] = strtoupper((string) $rate['currency']);
                }

                return $rate;
            })
            ->all();

        $this->merge(['rates' => $rates]);
    }
}