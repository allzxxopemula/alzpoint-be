<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\BusinessProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            ['full_name' => 'Administrator Alz Point', 'role' => 'admin', 'password' => 'password'],
        );
        $business = app(BusinessProvisioner::class)->provision($admin);

        User::updateOrCreate(
            ['username' => 'kasir'],
            ['full_name' => 'Nadia Putri', 'role' => 'kasir', 'password' => 'password', 'business_id' => $business->business_id],
        );
        User::updateOrCreate(
            ['username' => 'pelanggan'],
            ['full_name' => 'Akun Pelanggan Bersama', 'role' => 'pelanggan', 'password' => 'password', 'business_id' => $business->business_id],
        );

        $categories = collect([
            ['category_name' => 'Makanan', 'description' => 'Roti dan makanan siap santap'],
            ['category_name' => 'Minuman', 'description' => 'Minuman dingin dan kemasan'],
            ['category_name' => 'Snack', 'description' => 'Camilan untuk waktu istirahat'],
            ['category_name' => 'Alat Tulis', 'description' => 'Perlengkapan belajar sehari-hari'],
        ])->mapWithKeys(fn (array $category) => [
            $category['category_name'] => Category::firstOrCreate(
                ['business_id' => $business->business_id, 'category_name' => $category['category_name']],
                ['description' => $category['description']],
            ),
        ]);

        $cashiers = User::where('role', 'kasir')->where('business_id', $business->business_id)->get();
        $customers = User::where('role', 'pelanggan')->where('business_id', $business->business_id)->get();
        $products = collect([
            ['Makanan', 'Roti Cokelat', 8000, 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af'],
            ['Makanan', 'Roti Keju', 9000, 'https://images.unsplash.com/photo-1509440159596-0249088772ff'],
            ['Makanan', 'Nasi Kuning Mini', 12000, 'https://images.unsplash.com/photo-1512058564366-18510be2db19'],
            ['Makanan', 'Donat Gula', 6000, 'https://images.unsplash.com/photo-1551024506-0bcad0d4b8a0'],
            ['Minuman', 'Es Teh Manis', 3000, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc'],
            ['Minuman', 'Air Mineral', 4000, 'https://images.unsplash.com/photo-1523362628745-0c100150b504'],
            ['Minuman', 'Susu Cokelat', 6500, 'https://images.unsplash.com/photo-1563636619-e9143da7973b'],
            ['Minuman', 'Jus Jeruk', 7000, 'https://images.unsplash.com/photo-1613478223719-2ab802602423'],
            ['Snack', 'Keripik Singkong', 5000, 'https://images.unsplash.com/photo-1566478989037-eec170784d0b'],
            ['Snack', 'Biskuit Cokelat', 4500, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35'],
            ['Snack', 'Wafer Vanila', 3500, 'https://images.unsplash.com/photo-1582176604856-e824b4736522'],
            ['Snack', 'Cokelat Bar', 7000, 'https://images.unsplash.com/photo-1548907040-4d42f6c45a71'],
            ['Alat Tulis', 'Buku Tulis 38 Lembar', 4500, 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c'],
            ['Alat Tulis', 'Pulpen Gel Hitam', 5000, 'https://images.unsplash.com/photo-1585336261026-675768871463'],
            ['Alat Tulis', 'Pensil 2B', 2500, 'https://images.unsplash.com/photo-1513364776144-60967b0f800f'],
            ['Alat Tulis', 'Penghapus Putih', 2000, 'https://images.unsplash.com/photo-1455390582262-044cdead277a'],
        ])->map(function (array $item) use ($categories, $business) {
            [$category, $name, $price, $imageUrl] = $item;
            $imageUrl = $imageUrl.'?auto=format&fit=crop&w=360&q=55';

            $product = Product::firstOrCreate(
                ['business_id' => $business->business_id, 'product_name' => $name],
                [
                    'category_id' => $categories[$category]->category_id,
                    'price' => $price,
                    'stock' => 450,
                    'image_url' => $imageUrl,
                    'status' => 'available',
                ],
            );

            if ($product->image_url !== $imageUrl) {
                $product->update(['image_url' => $imageUrl]);
            }

            return $product;
        })->values();

        if (Order::where('order_number', 'like', 'DEMO-%')->exists()) {
            return;
        }

        $stockLevels = $products->mapWithKeys(fn (Product $product) => [$product->product_id => $product->stock]);
        $baseDate = now()->startOfDay();

        for ($daysAgo = 44; $daysAgo >= 0; $daysAgo--) {
            $date = $baseDate->copy()->subDays($daysAgo);
            $ordersToday = $daysAgo === 0
                ? random_int(3, 6)
                : ($date->isWeekend() ? random_int(0, 2) : random_int(4, 8));

            for ($sequence = 1; $sequence <= $ordersToday; $sequence++) {
                $createdAt = $date->copy()->setTime(random_int(7, 15), random_int(0, 59));
                $statusRoll = random_int(1, 100);
                $status = $statusRoll <= 84 ? 'completed' : ($statusRoll <= 94 ? 'pending' : 'canceled');
                $paymentMethod = fake()->randomElement(['tunai', 'tunai', 'qris', 'transfer']);
                $customer = random_int(1, 100) <= 88 ? $customers->random() : null;
                $customerName = $customer
                    ? fake()->randomElement([
                        'Alya Maharani', 'Bima Saputra', 'Citra Lestari', 'Daffa Ramadhan',
                        'Intan Permata', 'Kevin Wijaya', 'Nabila Zahra', 'Rafi Firmansyah',
                    ])
                    : fake()->name();

                $availableProducts = $products
                    ->filter(fn (Product $product) => $stockLevels[$product->product_id] > 0)
                    ->shuffle()
                    ->take(random_int(1, 4));
                $items = [];
                $total = 0;

                foreach ($availableProducts as $product) {
                    $quantity = random_int(1, min(4, $stockLevels[$product->product_id]));
                    $subtotal = round((float) $product->price * $quantity, 2);
                    $items[] = compact('product', 'quantity', 'subtotal');
                    $total += $subtotal;
                }

                if ($items === []) {
                    continue;
                }

                $cashReceived = $paymentMethod === 'tunai'
                    ? $total + fake()->randomElement([0, 500, 1000, 2000, 5000, 10000])
                    : $total;
                $order = Order::create([
                    'business_id' => $business->business_id,
                    'order_number' => sprintf('DEMO-%s-%03d', $date->format('ymd'), $sequence),
                    'customer_id' => $customer?->user_id,
                    'customer_name' => $customerName,
                    'cashier_id' => $status === 'pending' ? null : $cashiers->random()->user_id,
                    'total_amount' => $total,
                    'cash_received' => $cashReceived,
                    'change_amount' => $paymentMethod === 'tunai' ? $cashReceived - $total : 0,
                    'status' => $status,
                    'payment_method' => $paymentMethod,
                    'created_at' => $createdAt,
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->order_id,
                        'product_id' => $item['product']->product_id,
                        'product_name' => $item['product']->product_name,
                        'quantity' => $item['quantity'],
                        'price' => $item['product']->price,
                        'subtotal' => $item['subtotal'],
                    ]);

                    if ($status === 'completed') {
                        $stockLevels[$item['product']->product_id] -= $item['quantity'];
                    }
                }
            }
        }

        foreach ($products as $product) {
            $product->stock = $stockLevels[$product->product_id];
            $product->status = $product->stock > 0 ? 'available' : 'out';
            $product->save();
        }
    }
}
