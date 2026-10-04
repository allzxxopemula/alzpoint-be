<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'kasir'], true)
            && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        return ['period' => ['sometimes', Rule::in(['today', 'week', 'month'])]];
    }
}
