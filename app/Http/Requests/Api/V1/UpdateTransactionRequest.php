<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id'),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateCategoryBelongsToAccountOwner($value, $fail);
                },
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateCategoryMatchesTransactionType($value, $fail);
                },
            ],
            'created_at' => 'required|date',
            'transaction_type' => 'nullable|string|in:DEBIT,CREDIT',
            'currency' => 'nullable|string|size:3',
            'note' => 'nullable|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('transaction_type')) {
            $this->merge([
                'transaction_type' => strtoupper($this->input('transaction_type')),
            ]);
        }
    }

    private function validateCategoryBelongsToAccountOwner(mixed $categoryId, \Closure $fail): void
    {
        if (! $categoryId) {
            return;
        }

        $account = Account::query()->accessibleTo($this->user())->find($this->input('account_id'));

        if (! $account) {
            return;
        }

        $category = Category::withoutGlobalScopes()->find($categoryId);

        if (! $category || (int) $category->user_id !== (int) $account->user_id) {
            $fail('The selected category is invalid for the chosen account.');
        }
    }

    private function validateCategoryMatchesTransactionType(mixed $categoryId, \Closure $fail): void
    {
        if (! $categoryId || ! $this->filled('transaction_type')) {
            return;
        }

        $category = Category::withoutGlobalScopes()->find($categoryId);

        if (! $category?->type) {
            return;
        }

        $expectedType = Transaction::transactionTypeForCategoryType($category->type);

        if ($this->input('transaction_type') !== $expectedType) {
            $fail("The selected category requires transaction_type {$expectedType}.");
        }
    }
}
