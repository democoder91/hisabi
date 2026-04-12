<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Budget\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
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
            'start_at' => ['required', 'date'],
            'end_at' => [
                Rule::requiredIf(fn () => $this->input('reoccurrence') === Budget::CUSTOM),
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
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
        ];
    }
}