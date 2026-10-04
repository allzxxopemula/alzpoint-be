<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteOrderItems(false);

            return;
        }

        if (! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name', 100)->nullable()->after('product_id');
            });
        }

        DB::table('order_items')
            ->orderBy('order_item_id')
            ->chunkById(500, function ($items): void {
                $productNames = DB::table('products')
                    ->whereIn('product_id', $items->pluck('product_id')->filter())
                    ->pluck('product_name', 'product_id');

                foreach ($items as $item) {
                    DB::table('order_items')->where('order_item_id', $item->order_item_id)->update([
                        'product_name' => $productNames[$item->product_id] ?? 'Produk dihapus',
                    ]);
                }
            }, 'order_item_id');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_product_id_foreign');
            $table->unsignedInteger('product_id')->nullable()->change();
            $table->string('product_name', 100)->nullable(false)->change();
            $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('order_items')->whereNull('product_id')->exists()) {
            throw new RuntimeException('Cannot restore product foreign keys while order items reference deleted products.');
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteOrderItems(true);

            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_product_id_foreign');
            $table->unsignedInteger('product_id')->nullable(false)->change();
            $table->dropColumn('product_name');
            $table->foreign('product_id')->references('product_id')->on('products')->restrictOnDelete();
        });
    }

    private function rebuildSqliteOrderItems(bool $rollback): void
    {
        $replacementTable = $rollback ? 'order_items_rollback' : 'order_items_replacement';

        Schema::create($replacementTable, function (Blueprint $table) use ($rollback) {
            $table->increments('order_item_id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id')->nullable(! $rollback);
            if (! $rollback) {
                $table->string('product_name', 100);
            }
            $table->unsignedInteger('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')
                ->{$rollback ? 'restrictOnDelete' : 'nullOnDelete'}();
        });

        DB::table('order_items')->orderBy('order_item_id')->chunkById(500, function ($items) use ($rollback, $replacementTable): void {
            $productNames = $rollback
                ? collect()
                : DB::table('products')
                    ->whereIn('product_id', $items->pluck('product_id')->filter())
                    ->pluck('product_name', 'product_id');

            $rows = $items->map(function (object $item) use ($rollback, $productNames): array {
                $row = [
                    'order_item_id' => $item->order_item_id,
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];

                if (! $rollback) {
                    $row['product_name'] = $productNames[$item->product_id] ?? 'Produk dihapus';
                }

                return $row;
            })->all();

            DB::table($replacementTable)->insert($rows);
        }, 'order_item_id');

        Schema::drop('order_items');
        Schema::rename($replacementTable, 'order_items');
    }
};
