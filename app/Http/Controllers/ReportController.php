<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesReportRequest;
use App\Http\Requests\MonthlyRevenueRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

class ReportController extends Controller
{
    public function monthly(MonthlyRevenueRequest $request)
    {
        $timezone = 'Asia/Jakarta';
        $businessId = $request->user()->business_id;
        abort_unless($businessId, 403);
        $now = CarbonImmutable::now($timezone);
        $currentYear = $now->year;
        $year = (int) ($request->validated('year') ?? $currentYear);
        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone);
        $yearEnd = $year === $currentYear ? $now : $yearStart->endOfYear();
        $firstOrderDate = Order::where('business_id', $businessId)->where('status', 'completed')->min('created_at');
        $firstYear = $firstOrderDate
            ? CarbonImmutable::parse($firstOrderDate, 'UTC')->setTimezone($timezone)->year
            : $currentYear;
        $firstYear = max(2000, min($firstYear, $currentYear));
        $availableYears = range($currentYear, $firstYear, -1);

        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $monthLimit = $year === $currentYear ? $now->month : 12;
        $months = [];

        for ($month = 1; $month <= $monthLimit; $month++) {
            $months[$month] = [
                'month' => $month,
                'label' => $monthNames[$month - 1],
                'revenue' => 0.0,
                'transactions' => 0,
            ];
        }

        $orders = Order::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$yearStart->utc(), $yearEnd->utc()])
            ->orderBy('created_at')
            ->get(['order_id', 'total_amount', 'created_at']);

        foreach ($orders as $order) {
            $createdAt = CarbonImmutable::parse($order->created_at, 'UTC')->setTimezone($timezone);
            $month = $createdAt->month;

            if (isset($months[$month])) {
                $months[$month]['revenue'] += (float) $order->total_amount;
                $months[$month]['transactions']++;
            }
        }

        $monthData = array_values($months);
        $totalRevenue = array_sum(array_column($monthData, 'revenue'));
        $transactionCount = array_sum(array_column($monthData, 'transactions'));
        $bestMonth = collect($monthData)
            ->filter(fn (array $month) => $month['revenue'] > 0)
            ->sortByDesc('revenue')
            ->first();

        return response()->json([
            'data' => [
                'year' => $year,
                'available_years' => $availableYears,
                'total_revenue' => $totalRevenue,
                'transaction_count' => $transactionCount,
                'average_transaction' => $transactionCount ? $totalRevenue / $transactionCount : 0,
                'best_month' => $bestMonth,
                'months' => $monthData,
            ],
        ]);
    }

    public function sales(SalesReportRequest $request)
    {
        $period = $request->validated('period') ?? 'month';
        $businessId = $request->user()->business_id;
        abort_unless($businessId, 403);
        $timezone = 'Asia/Jakarta';
        $now = CarbonImmutable::now($timezone);
        [$start, $end, $periodDays] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay(), 1],
            'week' => [$now->startOfDay()->subDays(6), $now->endOfDay(), 7],
            default => [$now->startOfDay()->subDays(29), $now->endOfDay(), 30],
        };

        $orders = Order::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start->utc(), $end->utc()])
            ->orderBy('created_at')
            ->get(['order_id', 'total_amount', 'created_at']);
        $orderIds = $orders->pluck('order_id');
        $revenue = (float) $orders->sum('total_amount');
        $transactionCount = $orders->count();

        $topProducts = OrderItem::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as units_sold, SUM(subtotal) as revenue')
            ->whereIn('order_id', $orderIds)
            ->with('product:product_id,product_name,category_id')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product?->product_name ?? 'Produk dihapus',
                'category' => $item->product?->category?->category_name ?? 'Tanpa kategori',
                'sold' => (int) $item->units_sold,
                'total' => (float) $item->revenue,
            ]);

        $topCategory = OrderItem::query()
            ->join('products', 'products.product_id', '=', 'order_items.product_id')
            ->join('categories', 'categories.category_id', '=', 'products.category_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->groupBy('categories.category_name')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->value('categories.category_name') ?? '-';

        $chartBuckets = [];
        if ($period === 'today') {
            for ($hour = 0; $hour < 24; $hour++) {
                $key = sprintf('%02d', $hour);
                $chartBuckets[$key] = [
                    'label' => sprintf('%02d:00', $hour),
                    'revenue' => 0.0,
                    'transactions' => 0,
                ];
            }
        } else {
            $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            for ($dayOffset = 0; $dayOffset < $periodDays; $dayOffset++) {
                $day = $start->addDays($dayOffset);
                $key = $day->format('Y-m-d');
                $label = $period === 'week'
                    ? $dayNames[$day->dayOfWeek].' '.$day->format('d/m')
                    : $day->format('d').' '.$monthNames[(int) $day->format('n') - 1];

                $chartBuckets[$key] = [
                    'label' => $label,
                    'revenue' => 0.0,
                    'transactions' => 0,
                ];
            }
        }

        foreach ($orders as $order) {
            $createdAt = CarbonImmutable::parse($order->created_at, 'UTC')->setTimezone($timezone);
            $key = $period === 'today' ? $createdAt->format('H') : $createdAt->format('Y-m-d');

            if (isset($chartBuckets[$key])) {
                $chartBuckets[$key]['revenue'] += (float) $order->total_amount;
                $chartBuckets[$key]['transactions']++;
            }
        }

        $chart = array_values($chartBuckets);

        $itemsSold = $orderIds->isEmpty()
            ? 0
            : (int) OrderItem::whereIn('order_id', $orderIds)->sum('quantity');

        return response()->json([
            'data' => [
                'period' => $period,
                'total_revenue' => $revenue,
                'average_transaction' => $transactionCount ? $revenue / $transactionCount : 0,
                'transaction_count' => $transactionCount,
                'average_per_day' => $transactionCount / $periodDays,
                'items_sold' => $itemsSold,
                'top_category' => $topCategory,
                'chart' => $chart,
                'top_products' => $topProducts,
            ],
        ]);
    }
}
