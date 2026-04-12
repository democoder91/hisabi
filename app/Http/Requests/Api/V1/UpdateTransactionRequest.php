<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Brand\Models\Brand;
use App\Domains\Transaction\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

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
            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
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

    private function validateBrandMatchesTransactionType(mixed $brandId, \Closure $fail): void
    {
        if (! $brandId || ! $this->filled('transaction_type')) {
            return;
        }

        $brand = Brand::query()->with('category')->find($brandId);

        if (! $brand?->category?->type) {
            return;
        }

        $expectedType = Transaction::transactionTypeForCategoryType($brand->category->type);

        if ($this->input('transaction_type') !== $expectedType) {
            $fail("The selected brand requires transaction_type {$expectedType}.");
        }
    }
}
