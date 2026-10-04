<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminProfileRequest;

class AdminProfileController extends Controller
{
    public function update(UpdateAdminProfileRequest $request)
    {
        $admin = $request->user();
        $admin->fill($request->validated())->save();

        return response()->json([
            'message' => 'Profil admin berhasil diperbarui.',
            'data' => $admin->refresh()->only(['user_id', 'username', 'full_name', 'role', 'business_id']),
        ]);
    }
}