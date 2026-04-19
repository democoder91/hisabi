<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Domains\Category\Models\Category;
use App\Domains\Transaction\Models\Transaction;
use App\Enums\Currency;
use App\Scopes\TenantScope;
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
            'create_reverse_transaction' => [
                'nullable',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->boolean('create_reverse_transaction')) {
                        return;
                    }

                    if ($this->resolvedPrimaryTransactionType() !== Transaction::TYPE_CREDIT) {
                        $fail('A reverse transaction can only be created for credit transactions.');
                    }
                },
            ],
            'reverse_account_id' => [
                Rule::requiredIf(fn() => $this->boolean('create_reverse_transaction')),
                'nullable',
                'integer',
                'different:account_id',
                Rule::exists('accounts', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateReverseAccountIsEditable($value, $fail);
                },
            ],
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

        if ($this->has('create_reverse_transaction')) {
            $this->merge([
                'create_reverse_transaction' => $this->boolean('create_reverse_transaction'),
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

    private function validateReverseAccountIsEditable(mixed $reverseAccountId, \Closure $fail): void
    {
        if (! $this->boolean('create_reverse_transaction') || ! $reverseAccountId) {
            return;
        }

        $account = Account::query()->accessibleTo($this->user())->find($reverseAccountId);

        if (! $account || ! $account->canBeEditedBy($this->user())) {
            $fail('The selected reverse account is invalid.');
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

    private function resolvedPrimaryTransactionType(): ?string
    {
        if ($this->filled('transaction_type')) {
            return strtoupper((string) $this->input('transaction_type'));
        }

        $categoryId = $this->input('category_id');

        if (! $categoryId) {
            return null;
        }

        $category = Category::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($categoryId);

        if (! $category || ! $category->type) {
            return null;
        }

        return Transaction::transactionTypeForCategoryType($category->type);
    }
}
