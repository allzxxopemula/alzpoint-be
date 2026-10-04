<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->record($product, 'Tambah Produk', "Produk {$product->product_name} berhasil ditambahkan.");
    }

    public function updated(Product $product): void
    {
        $labels = [
            'product_name' => 'nama',
            'category_id' => 'kategori',
            'price' => 'harga',
            'stock' => 'stok',
            'image_url' => 'gambar',
            'status' => 'status',
        ];
        $changes = array_intersect_key($product->getChanges(), $labels);
        if (! $changes) return;

        $details = [];
        foreach ($changes as $field => $value) {
            $details[] = $labels[$field].': '.$product->getOriginal($field).' -> '.$value;
        }

        $this->record($product, 'Edit Produk', "Produk {$product->product_name} diperbarui (".implode(', ', $details).').');
    }

    public function deleted(Product $product): void
    {
        $this->record($product, 'Hapus Produk', "Produk {$product->product_name} berhasil dihapus.");
    }

    private function record(Product $product, string $action, string $description): void
    {
        $user = Auth::user();
        if (! $user?->business_id) return;

        ActivityLog::create([
            'business_id' => $user->business_id,
            'user_id' => $user->user_id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}