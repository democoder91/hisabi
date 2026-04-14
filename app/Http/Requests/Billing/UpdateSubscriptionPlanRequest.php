<?php

namespace App\Http\Requests\Billing;

use App\Models\BillingProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->isSuperUser();
    }

    public function rules(): array
    {
        /** @var BillingProduct|null $subscriptionPlan */
        $subscriptionPlan = $this->route('subscriptionPlan');

        return [
            'slug' => ['required', 'string', 'max:255', Rule::unique('billing_products', 'slug')->ignore($subscriptionPlan)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'price' => ['required', 'integer', 'min:1'],
            'credits' => ['required', 'integer', 'min:1'],
            'renews_in_days' => ['required', 'integer', 'min:1'],
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
}