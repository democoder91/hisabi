<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTransactionRequest extends FormRequest
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
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateBrandBelongsToAccountOwner($value, $fail);
                },
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateBrandMatchesTransactionType($value, $fail);
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

    private function validateBrandBelongsToAccountOwner(mixed $brandId, \Closure $fail): void
    {
        if (! $brandId) {
            return;
        }

        $account = Account::query()->accessibleTo($this->user())->find($this->input('account_id'));

        if (! $account) {
            return;
        }

        $brand = Brand::withoutGlobalScopes()->find($brandId);

        if (! $brand || (int) $brand->user_id !== (int) $account->user_id) {
            $fail('The selected brand is invalid for the chosen account.');
        }
    }

    private function validateBrandMatchesTransactionType(mixed $brandId, \Closure $fail): void
    {
        if (! $brandId || ! $this->filled('transaction_type')) {
            return;
        }

        $brand = Brand::withoutGlobalScopes()->with('category')->find($brandId);

        if (! $brand?->category?->type) {
            return;
        }

        $expectedType = Transaction::transactionTypeForCategoryType($brand->category->type);

        if ($this->input('transaction_type') !== $expectedType) {
            $fail("The selected brand requires transaction_type {$expectedType}.");
        }
    }
}
