<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Budget\Models\Budget;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'start_at' => ['required', 'date'],
            'end_at' => [
                Rule::requiredIf(fn() => $this->input('reoccurrence') === Budget::CUSTOM),
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
            'saving' => ['nullable', 'boolean'],
            'period' => ['required', 'integer', 'min:1'],
            'reoccurrence' => ['required', 'string', Rule::in([
                Budget::CUSTOM,
                Budget::DAILY,
                Budget::WEEKLY,
                Budget::MONTHLY,
                Budget::YEARLY,
            ])],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => [
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Account::query()->accessibleTo($this->user())->find((int) $value)) {
                        $fail('The selected account is invalid.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge([
                'currency' => strtoupper((string) $this->input('currency')),
            ]);
        }
    }
}
