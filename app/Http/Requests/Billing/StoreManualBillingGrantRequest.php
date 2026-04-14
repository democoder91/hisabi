<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualBillingGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->isSuperUser();
    }

    public function rules(): array
    {
        return [
            'billing_product_id' => [
                'required',
                'integer',
                Rule::exists('billing_products', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
