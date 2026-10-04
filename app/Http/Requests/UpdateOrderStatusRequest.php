<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'kasir'], true)
            && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['completed', 'canceled'])],
            'cash_received' => ['required_if:status,completed', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
