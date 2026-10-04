<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->business_id, 403);

        return response()->json([
            'data' => Discount::where('business_id', $request->user()->business_id)
                ->orderBy('min_spend')
                ->get(),
        ]);
    }

    public function store(StoreDiscountRequest $request)
    {
        $discount = Discount::create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return response()->json(['message' => 'Rule diskon berhasil ditambahkan.', 'data' => $discount], 201);
    }

    public function destroy(Request $request, int $discountId)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);

        Discount::where('business_id', $request->user()->business_id)
            ->findOrFail($discountId)
            ->delete();

        return response()->json(['message' => 'Rule diskon berhasil dihapus.']);
    }
}