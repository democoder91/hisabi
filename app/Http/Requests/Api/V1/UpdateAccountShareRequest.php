<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Account\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_level' => ['required', Rule::in([Account::PERMISSION_VIEW, Account::PERMISSION_EDIT])],
        ];
    }
}