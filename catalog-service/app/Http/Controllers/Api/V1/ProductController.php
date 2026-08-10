<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.sku' => 'required|string|max:100',
            'items.*.barcode' => 'nullable|string|max:100',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.category_id' => 'nullable|string|max:36',
            'items.*.category_name' => 'nullable|string|max:255',
            'items.*.min_reorder_point' => 'nullable|integer|min:0',
            'items.*.description' => 'nullable|string',
            'items.*.initial_stock' => 'nullable|integer|min:0',
        ]);

        $createdCount = 0;
        $errors = [];

        DB::transaction(function () use ($validated, &$createdCount, &$errors) {
            foreach ($validated['items'] as $index => $item) {
                $existing = DB::table('products')->where('sku', $item['sku'])->first();
                if ($existing) {
                    $errors[] = "Row #" . ($index + 1) . ": SKU '{$item['sku']}' already exists.";
                    continue;
                }

                $categoryId = $item['category_id'] ?? null;
                if (!$categoryId && !empty($item['category_name'])) {
                    $cat = DB::table('categories')->where('name', $item['category_name'])->first();
                    if ($cat) {
                        $categoryId = $cat->id;
                    } else {
                        $categoryId = (string) Str::uuid();
                        DB::table('categories')->insert([
                            'id' => $categoryId,
                            'name' => $item['category_name'],
                            'slug' => Str::slug($item['category_name']) . '-' . rand(100, 999),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $productId = (string) Str::uuid();
                DB::table('products')->insert([
                    'id' => $productId,
                    'category_id' => $categoryId,
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'barcode' => $item['barcode'] ?? null,
                    'description' => $item['description'] ?? null,
                    'cost_price' => $item['cost_price'] ?? 0.00,
                    'selling_price' => $item['selling_price'],
                    'min_reorder_point' => $item['min_reorder_point'] ?? 5,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $initialStock = $item['initial_stock'] ?? 0;
                DB::table('inventory_balances')->insert([
                    'id' => (string) Str::uuid(),
                    'outlet_id' => 1,
                    'product_id' => $productId,
                    'on_hand' => $initialStock,
                    'reserved' => 0,
                    'available' => $initialStock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdCount++;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => "Successfully imported {$createdCount} products.",
            'created_count' => $createdCount,
            'errors' => $errors,
        ]);
    }
    public function index(Request $request)
    {
        $outletId = $request->query('outlet_id', 1);
        $categoryId = $request->query('category_id');
        $search = $request->query('q');
        $stockStatus = $request->query('stock_status');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

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
                  ->orWhere('p.sku', 'like', "%{$search}%")
                  ->orWhere('p.barcode', 'like', "%{$search}%");
            });
        }

        if ($stockStatus === 'in_stock') {
            $query->where(DB::raw('COALESCE(ib.on_hand, 0)'), '>', 0);
        } elseif ($stockStatus === 'low_stock') {
            $query->where(DB::raw('COALESCE(ib.on_hand, 0)'), '<=', DB::raw('COALESCE(p.min_reorder_point, 5)'))
                  ->where(DB::raw('COALESCE(ib.on_hand, 0)'), '>', 0);
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where(DB::raw('COALESCE(ib.on_hand, 0)'), '<=', 0);
        }

        // Sorting
        if ($sortBy === 'name') {
            $query->orderBy('p.name', $sortOrder);
        } elseif ($sortBy === 'price') {
            $query->orderBy('p.selling_price', $sortOrder);
        } elseif ($sortBy === 'stock') {
            $query->orderBy(DB::raw('COALESCE(ib.on_hand, 0)'), $sortOrder);
        } else {
            $query->orderBy('p.created_at', $sortOrder)->orderBy('p.id', $sortOrder);
        }

        $products = $query->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.barcode',
                'p.description',
                'p.selling_price as price',
                'p.cost_price',
                'p.image_url',
                'p.min_reorder_point',
                'p.category_id',
                'c.name as category',
                DB::raw('COALESCE(ib.on_hand, 0) as stock_on_hand')
            )
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
