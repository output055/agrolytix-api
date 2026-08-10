<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessId = auth()->user()->business_id;

        $query = Product::where('business_id', $businessId)
            ->with('units')
            ->withSum('retailSaleItems as sales_count', 'quantity_base');

        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.category', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->input('category') !== 'All' && $request->input('category')) {
            $query->where('products.category', $request->input('category'));
        }

        if ($request->has('sort_column') && $request->input('sort_column')) {
            $column = $request->input('sort_column');
            $direction = $request->input('sort_direction', 'asc');
            $query->orderBy($column, $direction);
        } else {
            $query->orderByDesc('sales_count')
                  ->orderBy('products.name');
        }

        if ($request->has('paginate')) {
            $perPage = $request->input('per_page', 10);
            $paginated = $query->paginate($perPage);

            $stats = [
                'total_cost_value' => (float) Product::where('business_id', $businessId)->sum(DB::raw('quantity * cost_price')),
                'total_selling_value' => (float) Product::where('business_id', $businessId)->sum(DB::raw('quantity * sell_price')),
                'low_stock_count' => (int) Product::where('business_id', $businessId)->whereRaw('quantity <= COALESCE(low_stock_alert, 0)')->count(),
            ];

            return response()->json(array_merge($paginated->toArray(), ['stats' => $stats]));
        }

        return response()->json($query->get());
    }

    public function categories(Request $request): JsonResponse
    {
        $businessId = auth()->user()->business_id;
        $categories = Product::where('business_id', $businessId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        return response()->json($categories);
    }


    public function store(Request $request): JsonResponse
    {
        $this->adminOnly($request);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'category'         => 'nullable|string',
            'description'      => 'nullable|string',
            'cost_price'       => 'required|numeric|min:0',
            'sell_price'       => 'required|numeric|min:0',
            'quantity'         => 'required|integer|min:0',
            'base_unit'        => 'required|string',
            'low_stock_alert'  => 'nullable|integer|min:0',
            'units'            => 'nullable|array',
            'units.*.unit_name'        => 'required|string',
            'units.*.quantity_in_base' => 'required|integer|min:1',
            'units.*.price'            => 'required|numeric|min:0',
            'units.*.is_bulk'          => 'boolean',
            'units.*.bulk_discount_pct' => 'numeric|min:0|max:100',
        ]);

        $product = Product::create($data);

        if (!empty($data['units'])) {
            $product->units()->createMany($data['units']);
        }

        return response()->json($product->load('units'), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('units'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->adminOnly($request);

        $data = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'category'         => 'nullable|string',
            'description'      => 'nullable|string',
            'cost_price'       => 'sometimes|numeric|min:0',
            'sell_price'       => 'sometimes|numeric|min:0',
            'base_unit'        => 'sometimes|string',
            'low_stock_alert'  => 'nullable|integer|min:0',
            'units'            => 'nullable|array',
            'units.*.unit_name'        => 'required|string',
            'units.*.quantity_in_base' => 'required|integer|min:1',
            'units.*.price'            => 'required|numeric|min:0',
            'units.*.is_bulk'          => 'boolean',
            'units.*.bulk_discount_pct' => 'numeric|min:0|max:100',
        ]);

        $product->update($data);

        if (isset($data['units'])) {
            $product->units()->delete();
            $product->units()->createMany($data['units']);
        }

        return response()->json($product->load('units'));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->adminOnly($request);
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function restock(Request $request, Product $product): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate(['quantity' => 'required|integer|min:1']);

        $product->quantity += $data['quantity'];
        $product->last_added_qty = $data['quantity'];
        $product->save();

        return response()->json($product->fresh('units'));
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');
    }
}
