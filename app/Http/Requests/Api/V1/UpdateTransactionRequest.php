<?php

namespace App\Http\Requests\Api\V1;

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
            'brand_id' => 'nullable|integer|exists:brands,id',
            'created_at' => 'required|date',
            'currency' => 'nullable|string|size:3',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
