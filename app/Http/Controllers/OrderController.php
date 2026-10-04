<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with([
            'customer:user_id,full_name,username',
            'cashier:user_id,full_name,username',
            'items.product:product_id,product_name,image_url',
        ])->where('business_id', $request->user()->business_id)
            ->latest('created_at')
            ->latest('order_id');

        if ($request->user()->role === 'pelanggan') {
            $orders->where('customer_id', $request->user()->user_id);
        }

        if ($request->filled('status')) {
            $orders->where('status', $request->query('status'));
        }

        return response()->json(['data' => $orders->limit(100)->get()]);
    }

    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        abort_unless($user->business_id, 403);

        $order = DB::transaction(function () use ($data, $user): Order {
            $productIds = collect($data['items'])->pluck('product_id')->sort()->values();
            $products = Product::where('business_id', $user->business_id)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->status !== 'available' || $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ['Produk tidak tersedia atau stok tidak mencukupi.'],
                    ]);
                }

                $subtotal += round((float) $product->price * $item['quantity'], 2);
            }

            $discountRule = Discount::where('business_id', $user->business_id)
                ->where('min_spend', '<=', $subtotal)
                ->orderByDesc('min_spend')
                ->first();
            $discountAmount = min((float) ($discountRule?->discount_amount ?? 0), $subtotal);
            $total = max(0, round($subtotal - $discountAmount, 2));
            $order = Order::create([
                'business_id' => $user->business_id,
                'order_number' => 'TRX-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'customer_id' => $user->role === 'pelanggan' ? $user->user_id : ($data['customer_id'] ?? null),
                'customer_name' => $data['customer_name'],
                'cashier_id' => null,
                'total_amount' => $total,
                'discount_amount' => $discountAmount,
                'cash_received' => 0,
                'change_amount' => 0,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'created_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $subtotal = round((float) $product->price * $item['quantity'], 2);
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibuat dan menunggu konfirmasi.',
            'data' => $order->load(['items.product', 'customer:user_id,full_name,username']),
        ], 201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId)
    {
        $order = DB::transaction(function () use ($request, $orderId): Order {
            $order = Order::where('business_id', $request->user()->business_id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Hanya pesanan pending yang dapat dikonfirmasi atau dibatalkan.'],
                ]);
            }

            $status = $request->validated('status');
            if ($status === 'completed') {
                $items = $order->items()->orderBy('product_id')->get();
                $products = Product::where('business_id', $order->business_id)
                    ->whereIn('product_id', $items->pluck('product_id'))
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    if (! $product || $product->status !== 'available' || $product->stock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'stock' => ["Stok {$product?->product_name} tidak mencukupi untuk konfirmasi."],
                        ]);
                    }
                }

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    $product->stock -= $item->quantity;
                    $product->status = $product->stock <= 0 ? 'out' : 'available';
                    $product->save();
                }

                $cashReceived = (float) $request->validated('cash_received');
                if ($order->payment_method === 'tunai' && $cashReceived < $order->total_amount) {
                    throw ValidationException::withMessages([
                        'cash_received' => ['Uang diterima belum mencukupi total pembayaran.'],
                    ]);
                }

                $order->cashier_id = $request->user()->user_id;
                $order->cash_received = $order->payment_method === 'tunai' ? $cashReceived : $order->total_amount;
                $order->change_amount = $order->payment_method === 'tunai'
                    ? $cashReceived - $order->total_amount
                    : 0;
            }

            $order->status = $status;
            $order->save();

            return $order;
        });

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => $order->load([
                'customer:user_id,full_name,username',
                'cashier:user_id,full_name,username',
                'items.product:product_id,product_name,image_url',
            ]),
        ]);
    }
}
