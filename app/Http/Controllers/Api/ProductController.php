<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('units')->orderBy('name')->get();
        return response()->json($products);
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
