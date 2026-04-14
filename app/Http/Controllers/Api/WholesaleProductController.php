<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WholesaleProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WholesaleProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WholesaleProduct::with('units')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->adminOnly($request);
        $data = $this->validateProduct($request);
        $product = WholesaleProduct::create($data);
        if (!empty($data['units'])) {
            $product->units()->createMany($data['units']);
        }
        return response()->json($product->load('units'), 201);
    }

    public function show(WholesaleProduct $wholesaleProduct): JsonResponse
    {
        return response()->json($wholesaleProduct->load('units'));
    }

    public function update(Request $request, WholesaleProduct $wholesaleProduct): JsonResponse
    {
        $this->adminOnly($request);
        $data = $this->validateProduct($request, true);
        $wholesaleProduct->update($data);
        if (isset($data['units'])) {
            $wholesaleProduct->units()->delete();
            $wholesaleProduct->units()->createMany($data['units']);
        }
        return response()->json($wholesaleProduct->load('units'));
    }

    public function destroy(Request $request, WholesaleProduct $wholesaleProduct): JsonResponse
    {
        $this->adminOnly($request);
        $wholesaleProduct->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function restock(Request $request, WholesaleProduct $wholesaleProduct): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate(['quantity' => 'required|integer|min:1']);
        $wholesaleProduct->increment('quantity', $data['quantity']);
        return response()->json($wholesaleProduct->fresh('units'));
    }

    private function validateProduct(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name'             => "$rule|string|max:255",
            'category'         => 'nullable|string',
            'description'      => 'nullable|string',
            'cost_price'       => "$rule|numeric|min:0",
            'sell_price'       => "$rule|numeric|min:0",
            'quantity'         => "$rule|integer|min:0",
            'base_unit'        => "$rule|string",
            'low_stock_alert'  => 'nullable|integer|min:0',
            'units'            => 'nullable|array',
            'units.*.unit_name'         => 'required|string',
            'units.*.quantity_in_base'  => 'required|integer|min:1',
            'units.*.price'             => 'required|numeric|min:0',
            'units.*.is_bulk'           => 'boolean',
            'units.*.bulk_discount_pct' => 'numeric|min:0|max:100',
        ]);
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');
    }
}
