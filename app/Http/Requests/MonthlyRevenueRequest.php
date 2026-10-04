<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'kasir'], true)
            && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        $currentYear = now('Asia/Jakarta')->year;

        return [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:'.$currentYear],
        ];
    }
}