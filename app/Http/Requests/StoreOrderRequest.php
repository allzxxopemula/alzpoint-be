<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'kasir', 'pelanggan'], true)
            && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_id' => [
                'nullable',
                Rule::exists('users', 'user_id')
                    ->where('role', 'pelanggan')
                    ->where('business_id', $this->user()->business_id),
            ],
            'payment_method' => ['required', Rule::in(['tunai', 'qris', 'transfer'])],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'product_id')->where('business_id', $this->user()->business_id),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
