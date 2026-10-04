<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->business_id !== null;
    }

public function rules(): array
{
    return [
        'full_name' => 'required|string|max:255',
        'username'  => 'required|string|max:50|unique:users,username', // TAMBAHKAN INI
        'password'  => 'required|string|min:8|confirmed',
    ];
}
}