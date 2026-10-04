<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return [
            'category_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'category_name')->where('business_id', $this->user()->business_id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}