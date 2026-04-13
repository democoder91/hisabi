<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->isSuperUser();
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3'],
            'credit_packages' => ['required', 'array', 'min:1'],
            'credit_packages.*.slug' => ['required', 'string', 'max:255'],
            'credit_packages.*.name_en' => ['required', 'string', 'max:255'],
            'credit_packages.*.name_ar' => ['required', 'string', 'max:255'],
            'credit_packages.*.price' => ['required', 'integer', 'min:1'],
            'credit_packages.*.credits' => ['required', 'integer', 'min:1'],
            'subscription_plans' => ['required', 'array', 'min:1'],
            'subscription_plans.*.slug' => ['required', 'string', 'max:255'],
            'subscription_plans.*.name_en' => ['required', 'string', 'max:255'],
            'subscription_plans.*.name_ar' => ['required', 'string', 'max:255'],
            'subscription_plans.*.price' => ['required', 'integer', 'min:1'],
            'subscription_plans.*.credits' => ['required', 'integer', 'min:1'],
            'subscription_plans.*.renews_in_days' => ['required', 'integer', 'min:1'],
        ];
    }
}
