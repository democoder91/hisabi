<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Enums\Currency;
use App\Scopes\TenantScope;
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
                Rule::requiredIf(fn() => ! $this->isLedgerTransferRequest()),
                'nullable',
                'integer',
                Rule::exists('accounts', 'id'),
            ],
            'category_id' => [
                Rule::requiredIf(fn() => ! $this->isLedgerTransferRequest()),
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateCategoryBelongsToAccountOwner($value, $fail);
                },
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateCategoryMatchesTransactionType($value, $fail);
                },
            ],
            'from_account_id' => [
                Rule::requiredIf(fn() => $this->filled('to_account_id')),
                'nullable',
                'integer',
                'different:to_account_id',
                Rule::exists('accounts', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateTransferAccountIsEditable($value, $fail);
                },
            ],
            'to_account_id' => [
                Rule::requiredIf(fn() => $this->filled('from_account_id')),
                'nullable',
                'integer',
                'different:from_account_id',
                Rule::exists('accounts', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateTransferAccountIsEditable($value, $fail);
                },
            ],
            'created_at' => 'required|date',
            'transaction_type' => 'nullable|string|in:DEBIT,CREDIT',
            'currency' => ['nullable', Rule::enum(Currency::class)],
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

        if ($this->filled('currency')) {
            $this->merge([
                'currency' => strtoupper((string) $this->input('currency')),
            ]);
        }
    }

    private function validateCategoryBelongsToAccountOwner(mixed $categoryId, \Closure $fail): void
    {
        if (! $categoryId) {
            return;
        }

        $category = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($categoryId);

        if (! $category) {
            $fail('The selected category is invalid for the chosen account.');

            return;
        }

        $ownerIds = collect([$this->input('account_id'), $this->input('from_account_id'), $this->input('to_account_id')])
            ->filter()
            ->map(function (mixed $accountId) {
                $account = Account::query()->accessibleTo($this->user())->find($accountId);

                return $account ? $account->user_id : null;
            })
            ->filter()
            ->map(fn(mixed $ownerId) => (int) $ownerId)
            ->unique()
            ->values();

        if ($ownerIds->isNotEmpty() && ! $ownerIds->contains((int) $category->user_id)) {
            $fail('The selected category is invalid for the chosen account.');
        }
    }

    private function validateCategoryMatchesTransactionType(mixed $categoryId, \Closure $fail): void
    {
        if (! $categoryId || ! $this->filled('transaction_type')) {
            return;
        }

        $category = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($categoryId);

        if (! $category || ! $category->type) {
            return;
        }

        $expectedType = Transaction::transactionTypeForCategoryType($category->type);

        if ($this->input('transaction_type') !== $expectedType) {
            $fail("The selected category requires transaction_type {$expectedType}.");
        }
    }

    private function validateTransferAccountIsEditable(mixed $accountId, \Closure $fail): void
    {
        if (! $accountId) {
            return;
        }

        $account = Account::query()->accessibleTo($this->user())->find($accountId);

        if (! $account || ! $account->canBeEditedBy($this->user())) {
            $fail('The selected transfer account is invalid.');
        }
    }

    private function isLedgerTransferRequest(): bool
    {
        return $this->filled('from_account_id') || $this->filled('to_account_id');
    }
}
