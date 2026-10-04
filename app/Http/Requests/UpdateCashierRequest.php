<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->business_id !== null;
    }

    public function rules(): array
    {
        // Ambil ID dari parameter rute {userId} di routes/api.php
        $userId = $this->route('userId');

        return [
            'full_name' => 'required|string|max:255',
            // PENTING: Tambahkan $userId dan 'user_id' agar Laravel tahu user ini sedang diedit sendiri
            'username'  => 'required|string|max:50|unique:users,username,' . $userId . ',user_id',
            'password'  => 'nullable|string|min:8|confirmed',
        ];
    }
}