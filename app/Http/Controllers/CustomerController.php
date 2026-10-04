<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        if (! in_array($request->user()->role, ['admin', 'kasir'], true) || ! $request->user()->business_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $orders = Order::query()
            ->where('business_id', $request->user()->business_id)
            ->where(fn ($query) => $query->whereNotNull('customer_id')->orWhereNotNull('customer_name'))
            ->with([
                'customer:user_id,full_name,username',
                'items.product:product_id,product_name',
            ])
            ->latest('created_at')
            ->latest('order_id')
            ->get();

        $customers = $orders
            ->groupBy(function (Order $order): string {
                $name = trim($order->customer_name ?: $order->customer?->full_name ?: 'Pelanggan Umum');
                $identity = $order->customer_id ? 'user:'.$order->customer_id : 'walk-in';

                return $identity.'|'.mb_strtolower($name);
            })
            ->map(function ($customerOrders, string $key): array {
                $latestOrder = $customerOrders->first();
                $name = trim($latestOrder->customer_name ?: $latestOrder->customer?->full_name ?: 'Pelanggan Umum');
                $completedOrders = $customerOrders->where('status', 'completed');

                return [
                    'customer_key' => $key,
                    'user_id' => $latestOrder->customer_id,
                    'username' => $latestOrder->customer?->username,
                    'full_name' => $name,
                    'total_transactions' => $completedOrders->count(),
                    'total_spent' => (float) $completedOrders->sum('total_amount'),
                    'last_transaction_at' => $latestOrder->created_at,
                    'orders' => $customerOrders->take(100)->map(fn (Order $order) => [
                        'order_id' => $order->order_id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'status' => $order->status,
                        'payment_method' => $order->payment_method,
                        'total_amount' => (float) $order->total_amount,
                        'created_at' => $order->created_at,
                        'items' => $order->items->map(fn ($item) => [
                            'product_name' => $item->product_name ?: ($item->product?->product_name ?? 'Produk dihapus'),
                            'quantity' => $item->quantity,
                        ]),
                    ]),
                ];
            })
            ->sortBy('full_name')
            ->values();

        $customersWithoutOrders = User::where('role', 'pelanggan')
            ->where('business_id', $request->user()->business_id)
            ->whereDoesntHave('customerOrders')
            ->orderBy('full_name')
            ->get(['user_id', 'username', 'full_name'])
            ->map(fn (User $customer) => [
                'customer_key' => 'user:'.$customer->user_id.'|'.mb_strtolower($customer->full_name),
                'user_id' => $customer->user_id,
                'username' => $customer->username,
                'full_name' => $customer->full_name,
                'total_transactions' => 0,
                'total_spent' => 0,
                'last_transaction_at' => null,
                'orders' => collect(),
            ]);

        $customers = $customers->concat($customersWithoutOrders)
            ->sortByDesc(fn (array $customer): int => $customer['last_transaction_at']?->getTimestamp() ?? 0)
            ->values();
        $totalCustomers = $customers->count();
        $displayedCustomers = $customers->take(100)->values();

        return response()->json([
            'data' => [
                'total_customers' => $totalCustomers,
                'total_spent' => (float) $orders->where('status', 'completed')->sum('total_amount'),
                'customers' => $displayedCustomers,
            ],
        ]);
    }
}
