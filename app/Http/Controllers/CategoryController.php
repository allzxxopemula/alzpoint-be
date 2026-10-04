<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->business_id, 403);

        return response()->json([
            'data' => Category::where('business_id', $request->user()->business_id)
                ->orderBy('category_name')
                ->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return response()->json(['message' => 'Kategori berhasil ditambahkan.', 'data' => $category], 201);
    }

    public function destroy(Request $request, int $categoryId)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);

        $category = Category::where('business_id', $request->user()->business_id)->findOrFail($categoryId);
        if ($category->is_default) {
            return response()->json(['message' => 'Kategori bawaan tidak dapat dihapus.'], 409);
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Kategori masih dipakai produk dan tidak dapat dihapus.'], 409);
        }

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
