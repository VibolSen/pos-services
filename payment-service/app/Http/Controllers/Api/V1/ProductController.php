<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->query('outlet_id', 1);
        $categoryId = $request->query('category_id');
        $search = $request->query('q');

        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('inventory_balances as ib', function ($join) use ($outletId) {
                $join->on('p.id', '=', 'ib.product_id')
                    ->where('ib.outlet_id', '=', $outletId);
            })
            ->where('p.is_active', true);

        if (!empty($categoryId)) {
            $query->where('p.category_id', $categoryId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.sku', 'like', "%{$search}%");
            });
        }

        $products = $query->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.description',
                'p.selling_price as price',
                'p.cost_price',
                'p.image_url',
                'p.category_id',
                'c.name as category',
                DB::raw('COALESCE(ib.on_hand, 0) as stock_on_hand')
            )
            ->orderBy('p.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    public function show($id)
    {
        $product = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('p.id', $id)
            ->select('p.*', 'c.name as category_name')
            ->first();

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function showByBarcode($code)
    {
        $product = DB::table('barcodes as b')
            ->join('products as p', 'b.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('b.barcode', $code)
            ->where('p.is_active', true)
            ->select('p.id', 'p.name', 'p.sku', 'p.selling_price as price', 'c.name as category', 'b.barcode')
            ->first();

        if (!$product) {
            // Fallback: Check if code matches product SKU
            $product = DB::table('products as p')
                ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
                ->where('p.sku', $code)
                ->where('p.is_active', true)
                ->select('p.id', 'p.name', 'p.sku', 'p.selling_price as price', 'c.name as category')
                ->first();
        }

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'No product found matching barcode ' . $code], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'initial_stock' => 'nullable|integer|min:0',
        ]);

        $productId = DB::table('products')->insertGetId([
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'selling_price' => $validated['selling_price'],
            'cost_price' => $validated['cost_price'] ?? 0.00,
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialStock = $validated['initial_stock'] ?? 100;
        DB::table('inventory_balances')->insert([
            'outlet_id' => 1,
            'product_id' => $productId,
            'on_hand' => $initialStock,
            'reserved' => 0,
            'available' => $initialStock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully.',
            'data' => ['id' => $productId],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|nullable|integer',
            'description' => 'sometimes|nullable|string',
        ]);

        $validated['updated_at'] = now();
        DB::table('products')->where('id', $id)->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        DB::table('products')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully.',
        ]);
    }
}
