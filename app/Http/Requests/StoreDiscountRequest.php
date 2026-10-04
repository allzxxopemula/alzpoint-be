<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'min_spend' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'discount_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }
}