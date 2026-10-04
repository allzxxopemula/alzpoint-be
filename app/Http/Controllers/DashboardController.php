<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        if ($request->user()->role === 'pelanggan') {
            return response()->json(['message' => 'Statistik hanya tersedia untuk admin dan kasir.'], 403);
        }

        $businessId = $request->user()->business_id;
        abort_unless($businessId, 403);

        $completedToday = fn ($query) => $query
            ->where('business_id', $businessId)
            ->whereDate('created_at', today())
            ->where('status', 'completed');

        $totalRevenue = Order::where($completedToday)->sum('total_amount');
        $totalOrders = Order::where('business_id', $businessId)->whereDate('created_at', today())->count();
        $itemsSold = OrderItem::whereHas('order', $completedToday)->sum('quantity');

        $recentOrders = Order::with([
            'customer:user_id,full_name',
            'cashier:user_id,full_name',
            'items.product:product_id,product_name',
        ])->where('business_id', $businessId)->latest('created_at')->latest('order_id')->limit(100)->get();

        return response()->json([
            'data' => [
                'total_pendapatan' => (float) $totalRevenue,
                'total_transaksi' => $totalOrders,
                'item_terjual' => (int) $itemsSold,
                'pesanan_pending' => Order::where('business_id', $businessId)->whereDate('created_at', today())->where('status', 'pending')->count(),
                'transaksi_terakhir' => $recentOrders,
            ],
        ]);
    }
}
