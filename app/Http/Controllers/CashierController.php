<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashierRequest;
use App\Http\Requests\UpdateCashierRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CashierController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);

        $cashiers = User::where('business_id', $request->user()->business_id)
            ->where('role', 'kasir')
            ->orderBy('full_name')
            ->get(['user_id', 'username', 'full_name', 'created_at']);

        return response()->json(['data' => $cashiers]);
    }

    public function store(StoreCashierRequest $request)
    {
        $data = $request->validated();

        // FIX: Tangkap username dari inputan React.
        // Jika kosong, baru buat otomatis dari nama.
        if (!empty($data['username'])) {
            $username = $data['username'];
        } else {
            $baseUsername = Str::limit(Str::slug($data['full_name'], '.'), 42, '');
            $baseUsername = $baseUsername !== '' ? $baseUsername : 'kasir';
            $username = $baseUsername;
            $suffix = 1;

            while (User::where('username', $username)->exists()) {
                $username = Str::limit($baseUsername, 45, '').$suffix;
                $suffix++;
            }
        }

        $cashier = User::create([
            'business_id' => $request->user()->business_id,
            'username' => $username,
            'password' => $data['password'], 
            'full_name' => $data['full_name'],
            'role' => 'kasir',
        ]);

        return response()->json([
            'message' => 'Akun kasir berhasil dibuat.',
            'data' => $cashier->only(['user_id', 'username', 'full_name', 'role', 'business_id']),
        ], 201);
    }

    public function update(UpdateCashierRequest $request, int $userId)
    {
        $cashier = User::where('business_id', $request->user()->business_id)
            ->where('role', 'kasir')
            ->findOrFail($userId);
            
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // FIX: Method fill akan otomatis memasukkan 'username' dan 'full_name' ke database
        $cashier->fill($data)->save();

        return response()->json([
            'message' => 'Data kasir berhasil diperbarui.',
            'data' => $cashier->refresh()->only(['user_id', 'username', 'full_name', 'role', 'business_id']),
        ]);
    }

    public function destroy(Request $request, int $userId)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);

        $cashier = User::where('business_id', $request->user()->business_id)
            ->where('role', 'kasir')
            ->findOrFail($userId);
        $cashier->delete();

        return response()->json(['message' => 'Akun kasir berhasil dihapus.']);
    }
}