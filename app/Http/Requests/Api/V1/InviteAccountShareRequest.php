<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteAccountShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === $this->user()?->email) {
                        $fail('You cannot share an account with yourself.');
                    }
                },
            ],
            'permission_level' => ['required', Rule::in([Account::PERMISSION_VIEW, Account::PERMISSION_EDIT])],
        ];
    }
}