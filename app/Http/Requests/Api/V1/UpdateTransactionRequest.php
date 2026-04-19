<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use App\Enums\Currency;
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
            'account_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'category_type' => ['prohibited'],
            'from_account_id' => [
                'required',
                'integer',
                'different:to_account_id',
                Rule::exists('accounts', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateTransferAccountIsEditable($value, $fail);
                },
            ],
            'to_account_id' => [
                'required',
                'integer',
                'different:from_account_id',
                Rule::exists('accounts', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $this->validateTransferAccountIsEditable($value, $fail);
                },
            ],
            'created_at' => 'required|date',
            'transaction_type' => ['prohibited'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'note' => 'nullable|string|max:1000',
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
}
