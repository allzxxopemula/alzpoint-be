<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'category_id')->where('business_id', $this->user()->business_id),
            ],
            'product_name' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'decimal:0,2'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'image_url' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'in:available,out'],
        ];
    }
}
