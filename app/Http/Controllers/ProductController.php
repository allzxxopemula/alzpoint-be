<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->business_id, 403);

        $products = Product::where('business_id', $request->user()->business_id)
            ->with('category')
            ->orderBy('product_name');

        $includeOutOfStock = $request->boolean('include_out') && $request->user()->role === 'admin';
        if (! $includeOutOfStock) {
            $products->where('status', 'available')->where('stock', '>', 0);
        }

        return response()->json(['data' => $products->get()]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['business_id'] = $request->user()->business_id;
        $data['status'] = $data['stock'] <= 0 ? 'out' : 'available';

        $product = Product::create($data)->load('category');

        return response()->json(['message' => 'Produk berhasil dibuat.', 'data' => $product], 201);
    }

    public function update(UpdateProductRequest $request, int $productId)
    {
        $product = Product::where('business_id', $request->user()->business_id)->findOrFail($productId);
        $data = $request->validated();
        $stock = $data['stock'] ?? $product->stock;
        $data['status'] = $stock <= 0 ? 'out' : ($data['status'] ?? 'available');

        $product->update($data);

        return response()->json(['message' => 'Produk berhasil diperbarui.', 'data' => $product->load('category')]);
    }

    public function destroy(Request $request, int $productId)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);
        $product = Product::where('business_id', $request->user()->business_id)->findOrFail($productId);

        try {
            $product->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Produk yang sudah tercatat pada transaksi tidak dapat dihapus.'], 409);
        }

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}
