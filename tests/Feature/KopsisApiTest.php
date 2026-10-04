<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeKopsisUser(string $username, string $role): User
{
    $businessAdmin = null;
    if ($role !== 'admin') {
        $businessAdmin = User::where('role', 'admin')->orderBy('user_id')->first();
        if (! $businessAdmin) {
            $businessAdmin = User::create([
                'username' => 'tenant-admin-fixture',
                'password' => Hash::make('secret123'),
                'full_name' => 'Admin Fixture',
                'role' => 'admin',
            ]);
        }
    }

    return User::create([
        'username' => $username,
        'password' => Hash::make('secret123'),
        'full_name' => ucfirst($username),
        'role' => $role,
        'business_id' => $businessAdmin?->business_id,
    ]);
}

function makeKopsisProduct(int $stock = 2): Product
{
    $businessId = User::where('role', 'admin')->orderBy('user_id')->value('business_id');
    $category = Category::firstOrCreate(
        ['business_id' => $businessId, 'category_name' => 'Makanan'],
        ['description' => 'Makanan untuk test', 'is_default' => true],
    );

    return Product::create([
        'business_id' => $businessId,
        'category_id' => $category->category_id,
        'product_name' => 'Roti Sekolah',
        'price' => 2500,
        'stock' => $stock,
        'image_url' => 'https://example.test/roti.jpg',
        'status' => $stock > 0 ? 'available' : 'out',
    ]);
}

test('user dapat login dan menerima token serta role', function () {
    makeKopsisUser('kasir1', 'kasir');

    $this->postJson('/api/login', ['username' => 'kasir1', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.user.username', 'kasir1')
        ->assertJsonPath('data.user.role', 'kasir')
        ->assertJsonStructure(['data' => ['token', 'user' => ['user_id', 'full_name']]]);
});

test('admin dapat menyimpan identitas Kopsis untuk ditampilkan pada struk', function () {
    $admin = makeKopsisUser('admin1', 'admin');
    Sanctum::actingAs($admin);

    $this->putJson('/api/settings/cooperative', [
        'cooperative_name' => 'Kopsis SMP Negeri 1',
        'address' => 'Jl. Pendidikan No. 12',
        'phone' => '081234567890',
        'email' => 'kopsis@example.test',
        'receipt_footer' => 'Terima kasih sudah berbelanja.',
    ])->assertOk()
        ->assertJsonPath('data.id', 1)
        ->assertJsonPath('data.cooperative_name', 'Kopsis SMP Negeri 1');

    $this->getJson('/api/settings/cooperative')
        ->assertOk()
        ->assertJsonPath('data.address', 'Jl. Pendidikan No. 12')
        ->assertJsonPath('data.receipt_footer', 'Terima kasih sudah berbelanja.');

    $this->assertDatabaseHas('cooperative_settings', [
        'id' => 1,
        'cooperative_name' => 'Kopsis SMP Negeri 1',
    ]);
});

test('order pending dapat dikonfirmasi dan memotong stok satu kali', function () {
    $customer = makeKopsisUser('siswa1', 'pelanggan');
    $cashier = makeKopsisUser('kasir1', 'kasir');
    $product = makeKopsisProduct();

    Sanctum::actingAs($customer);
    $checkout = $this->postJson('/api/orders', [
        'customer_name' => 'Nama di Order',
        'payment_method' => 'tunai',
        'cash_received' => 6000,
        'items' => [['product_id' => $product->product_id, 'quantity' => 2, 'price' => 2500]],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.customer_name', 'Nama di Order')
        ->assertJsonPath('data.customer.full_name', $customer->full_name)
        ->assertJsonPath('data.cash_received', '6000.00')
        ->assertJsonPath('data.change_amount', '1000.00');

    $orderId = $checkout->json('data.order_id');
    $this->assertDatabaseHas('users', ['user_id' => $customer->user_id, 'full_name' => 'Siswa1']);
    $this->assertDatabaseHas('products', ['product_id' => $product->product_id, 'stock' => 2]);

    Sanctum::actingAs($cashier);
    $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed'])
        ->assertOk()
        ->assertJsonPath('data.cashier.user_id', $cashier->user_id);

    $this->assertDatabaseHas('products', [
        'product_id' => $product->product_id,
        'stock' => 0,
        'status' => 'out',
    ]);

    $this->getJson('/api/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total_pendapatan', 5000)
        ->assertJsonPath('data.item_terjual', 2);

    $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed'])->assertUnprocessable();
    $this->assertDatabaseHas('products', ['product_id' => $product->product_id, 'stock' => 0]);
});

test('kasir dapat membuat order walk-in dengan nama pelanggan pada payload', function () {
    $cashier = makeKopsisUser('kasir1', 'kasir');
    $product = makeKopsisProduct();
    Sanctum::actingAs($cashier);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Nadia Siswa',
        'payment_method' => 'qris',
        'items' => [[
            'product_id' => $product->product_id,
            'quantity' => 1,
            'price' => 2500,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.customer_name', 'Nadia Siswa')
        ->assertJsonPath('data.customer', null);

    $this->getJson('/api/orders?status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.customer_name', 'Nadia Siswa');

    $this->assertDatabaseHas('orders', ['order_id' => $response->json('data.order_id'), 'status' => 'pending']);
});

test('walk-in menyimpan nama berbeda per order tanpa menimpa profil lain', function () {
    $cashier = makeKopsisUser('kasir1', 'kasir');
    $product = makeKopsisProduct();
    Sanctum::actingAs($cashier);

    foreach (['Siswa Pertama', 'Siswa Kedua'] as $customerName) {
        $this->postJson('/api/orders', [
            'customer_name' => $customerName,
            'payment_method' => 'qris',
            'items' => [[
                'product_id' => $product->product_id,
                'quantity' => 1,
                'price' => 2500,
            ]],
        ])->assertCreated()->assertJsonPath('data.customer_name', $customerName);
    }

    $orders = $this->getJson('/api/orders?status=pending')->assertOk()->json('data');
    $walkInNames = collect($orders)->pluck('customer_name')->all();

    expect($walkInNames)->toContain('Siswa Pertama', 'Siswa Kedua')
        ->and(User::where('role', 'pelanggan')->count())->toBe(0);
});

test('satu akun pelanggan dapat memakai nama checkout berbeda tanpa menimpa histori lama', function () {
    $customer = makeKopsisUser('siswa1', 'pelanggan');
    $cashier = makeKopsisUser('kasir1', 'kasir');
    $product = makeKopsisProduct();
    Sanctum::actingAs($customer);

    foreach (['Alya Maharani', 'Bima Saputra'] as $customerName) {
        $this->postJson('/api/orders', [
            'customer_name' => $customerName,
            'payment_method' => 'qris',
            'items' => [[
                'product_id' => $product->product_id,
                'quantity' => 1,
                'price' => 2500,
            ]],
        ])->assertCreated()->assertJsonPath('data.customer_name', $customerName);
    }

    $this->assertDatabaseHas('users', [
        'user_id' => $customer->user_id,
        'full_name' => 'Siswa1',
    ]);

    Sanctum::actingAs($cashier);
    $customerEntries = collect($this->getJson('/api/customers')->assertOk()->json('data.customers'))
        ->where('user_id', $customer->user_id)
        ->values();

    expect($customerEntries->pluck('full_name')->all())
        ->toContain('Alya Maharani', 'Bima Saputra')
        ->and($customerEntries)->toHaveCount(2)
        ->and($customerEntries->every(fn (array $entry) => count($entry['orders']) === 1))->toBeTrue();
});

test('produk yang dibuat dengan stok nol otomatis berstatus out', function () {
    $admin = makeKopsisUser('admin1', 'admin');
    $category = Category::firstOrCreate(
        ['business_id' => $admin->business_id, 'category_name' => 'Alat Tulis'],
        ['description' => 'Alat tulis test', 'is_default' => true],
    );
    Sanctum::actingAs($admin);

    $this->postJson('/api/products', [
        'category_id' => $category->category_id,
        'product_name' => 'Pensil',
        'price' => 1500,
        'stock' => 0,
        'image_url' => 'https://example.test/pensil.jpg',
    ])->assertCreated()->assertJsonPath('data.status', 'out');
});

test('produk dapat dihapus dan nama snapshotnya tetap pada order', function () {
    $admin = makeKopsisUser('admin1', 'admin');
    $product = makeKopsisProduct();
    Sanctum::actingAs($admin);

    $order = $this->postJson('/api/orders', [
        'customer_name' => 'Murid Contoh',
        'payment_method' => 'qris',
        'items' => [[
            'product_id' => $product->product_id,
            'quantity' => 1,
            'price' => 2500,
        ]],
    ])->assertCreated();

    $orderId = $order->json('data.order_id');
    $this->deleteJson("/api/products/{$product->product_id}")->assertOk();

    $this->assertDatabaseMissing('products', ['product_id' => $product->product_id]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => null,
        'product_name' => 'Roti Sekolah',
    ]);

    $this->getJson('/api/orders?status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.product_name', 'Roti Sekolah');
});

test('seeder menyediakan akun demo dan katalog awal', function () {
    $this->seed();

    expect(User::count())->toBe(3)
        ->and(Category::count())->toBe(4)
        ->and(Product::count())->toBe(16)
        ->and(Order::count())->toBeGreaterThan(100);

    $this->postJson('/api/login', ['username' => 'admin', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin');
});

test('api cors menerima origin vite lokal termasuk port alternatif', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:5174',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,authorization',
    ])->options('/api/login')
        ->assertNoContent()
        ->assertHeader('access-control-allow-origin', 'http://localhost:5174');
});

test('migration mengonversi users Laravel lama tanpa kehilangan nama atau password', function () {
    Schema::drop('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    DB::table('users')->insert([
        'name' => 'Legacy Kasir',
        'email' => 'legacy@example.test',
        'password' => Hash::make('secret123'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_01_130000_upgrade_users_table_for_kopsis_schema.php');
    $migration->up();

    $user = DB::table('users')->first();
    expect(Schema::hasColumn('users', 'user_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'username'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email'))->toBeFalse()
        ->and($user->username)->toBe('legacy_1')
        ->and($user->full_name)->toBe('Legacy Kasir')
        ->and(Hash::check('secret123', $user->password))->toBeTrue();
});

test('endpoint pelanggan mengembalikan ringkasan pembelian terkonfirmasi', function () {
    $customer = makeKopsisUser('siswa1', 'pelanggan');
    makeKopsisUser('siswa2', 'pelanggan');
    $cashier = makeKopsisUser('kasir1', 'kasir');

    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-CUSTOMER-1',
        'customer_id' => $customer->user_id,
        'total_amount' => 12500,
        'status' => 'confirmed',
        'payment_method' => 'tunai',
        'created_at' => now(),
    ]);

    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-CUSTOMER-2',
        'customer_id' => $customer->user_id,
        'total_amount' => 5000,
        'status' => 'pending',
        'payment_method' => 'qris',
        'created_at' => now(),
    ]);

    Sanctum::actingAs($cashier);
    $this->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.total_customers', 2)
        ->assertJsonPath('data.total_spent', 12500)
        ->assertJsonFragment([
            'user_id' => $customer->user_id,
            'total_transactions' => 1,
            'total_spent' => 12500,
        ]);
});

test('laporan menghitung hanya order terkonfirmasi pada periode yang dipilih', function () {
    $cashier = makeKopsisUser('kasir1', 'kasir');
    $product = makeKopsisProduct();

    $confirmedOrder = Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-REPORT-1',
        'total_amount' => 5000,
        'status' => 'confirmed',
        'payment_method' => 'tunai',
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $confirmedOrder->order_id,
        'product_id' => $product->product_id,
        'product_name' => $product->product_name,
        'quantity' => 2,
        'price' => 2500,
        'subtotal' => 5000,
    ]);

    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-REPORT-2',
        'total_amount' => 9000,
        'status' => 'pending',
        'payment_method' => 'qris',
        'created_at' => now(),
    ]);

    Sanctum::actingAs($cashier);
    $currentWibHour = (int) now('UTC')->setTimezone('Asia/Jakarta')->format('G');
    $this->getJson('/api/reports/sales?period=today')
        ->assertOk()
        ->assertJsonPath('data.total_revenue', 5000)
        ->assertJsonPath('data.transaction_count', 1)
        ->assertJsonPath('data.items_sold', 2)
        ->assertJsonPath('data.top_category', 'Makanan')
        ->assertJsonPath('data.top_products.0.name', 'Roti Sekolah')
        ->assertJsonCount(24, 'data.chart')
        ->assertJsonPath("data.chart.{$currentWibHour}.label", sprintf('%02d:00', $currentWibHour))
        ->assertJsonPath("data.chart.{$currentWibHour}.transactions", 1);

    $this->getJson('/api/reports/sales?period=week')
        ->assertOk()
        ->assertJsonCount(7, 'data.chart')
        ->assertJsonPath('data.chart.6.transactions', 1);

    $this->getJson('/api/reports/sales?period=month')
        ->assertOk()
        ->assertJsonCount(30, 'data.chart')
        ->assertJsonPath('data.chart.29.transactions', 1);
});

test('laporan bulanan merangkum pendapatan terkonfirmasi per bulan', function () {
    $cashier = makeKopsisUser('kasir-bulanan', 'kasir');
    $now = now('UTC');
    $year = now('Asia/Jakarta')->year;

    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-MONTH-1',
        'total_amount' => 5000,
        'status' => 'confirmed',
        'payment_method' => 'tunai',
        'created_at' => $now->copy()->setMonth(1)->setDay(15),
    ]);
    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-MONTH-2',
        'total_amount' => 7500,
        'status' => 'confirmed',
        'payment_method' => 'qris',
        'created_at' => $now->copy()->setMonth(2)->setDay(15),
    ]);
    Order::create([
        'business_id' => $cashier->business_id,
        'order_number' => 'TRX-MONTH-PENDING',
        'total_amount' => 12000,
        'status' => 'pending',
        'payment_method' => 'tunai',
        'created_at' => $now->copy()->setMonth(2)->setDay(16),
    ]);

    Sanctum::actingAs($cashier);
    $this->getJson("/api/reports/monthly?year={$year}")
        ->assertOk()
        ->assertJsonPath('data.year', $year)
        ->assertJsonPath('data.total_revenue', 12500)
        ->assertJsonPath('data.transaction_count', 2)
        ->assertJsonPath('data.months.0.label', 'Januari')
        ->assertJsonPath('data.months.0.revenue', 5000)
        ->assertJsonPath('data.months.1.label', 'Februari')
        ->assertJsonPath('data.months.1.revenue', 7500);
});

test('data produk kategori dan order terisolasi per tenant', function () {
    $adminOne = makeKopsisUser('owner-one', 'admin');
    $productOne = makeKopsisProduct();
    $categoryOne = Category::where('business_id', $adminOne->business_id)
        ->where('category_name', 'Makanan')
        ->firstOrFail();
    $adminTwo = makeKopsisUser('owner-two', 'admin');
    $categoryTwo = Category::where('business_id', $adminTwo->business_id)
        ->where('category_name', 'Makanan')
        ->firstOrFail();
    $productTwo = Product::create([
        'business_id' => $adminTwo->business_id,
        'category_id' => $categoryTwo->category_id,
        'product_name' => 'Produk Tenant Dua',
        'price' => 3000,
        'stock' => 5,
        'image_url' => 'https://example.test/tenant-two.jpg',
        'status' => 'available',
    ]);

    Sanctum::actingAs($adminOne);
    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $productOne->product_id);
    $this->postJson('/api/orders', [
        'customer_name' => 'Pelanggan Tenant Satu',
        'payment_method' => 'qris',
        'items' => [['product_id' => $productOne->product_id, 'quantity' => 1, 'price' => 2500]],
    ])->assertCreated();
    $customCategory = $this->postJson('/api/categories', [
        'category_name' => 'Menu Cafe',
        'description' => 'Menu khusus tenant satu',
    ])->assertCreated()->json('data');
    $this->deleteJson("/api/categories/{$categoryOne->category_id}")->assertConflict();

    Sanctum::actingAs($adminTwo);
    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $productTwo->product_id);
    $this->getJson('/api/orders')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonMissing(['category_id' => $customCategory['category_id']]);
});

test('admin membuat dan menghapus kasir hanya di tenant miliknya', function () {
    $adminOne = makeKopsisUser('owner-one', 'admin');
    $adminTwo = makeKopsisUser('owner-two', 'admin');

    Sanctum::actingAs($adminOne);
    $created = $this->postJson('/api/cashiers', [
        'full_name' => 'Nadia Kasir',
        'password' => 'password123',
    ])->assertCreated()
        ->assertJsonPath('data.full_name', 'Nadia Kasir')
        ->assertJsonPath('data.business_id', $adminOne->business_id);

    $cashierId = $created->json('data.user_id');
    $this->assertDatabaseHas('users', [
        'user_id' => $cashierId,
        'business_id' => $adminOne->business_id,
        'role' => 'kasir',
    ]);

    Sanctum::actingAs($adminTwo);
    $this->getJson('/api/cashiers')->assertOk()->assertJsonCount(0, 'data');
    $this->deleteJson("/api/cashiers/{$cashierId}")->assertNotFound();

    Sanctum::actingAs($adminOne);
    $this->deleteJson("/api/cashiers/{$cashierId}")->assertOk();
});
