<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\RetailSale;
use App\Models\RetailSaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetailPosController extends Controller
{
    /**
     * Process retail checkout.
     * Expects: { items: [{ product_id, product_unit_id|null, unit_name, quantity, unit_price, cost_price, quantity_base }] }
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.unit_name'         => 'required|string',
            'items.*.quantity'          => 'required|integer|min:1',
            'items.*.quantity_base'     => 'required|integer|min:1',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'items.*.cost_price'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $totalCost   = 0;

            // Validate stock before deducting
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->quantity < $item['quantity_base']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Insufficient stock for '{$product->name}'. Available: {$product->quantity} {$product->base_unit}(s)."
                    ], 422);
                }
            }

            // Create sale header
            $sale = RetailSale::create([
                'user_id'        => $request->user()->id,
                'receipt_number' => 'RET-' . strtoupper(Str::random(8)),
                'total_amount'   => 0,
                'total_cost'     => 0,
                'profit'         => 0,
                'status'         => 'completed',
            ]);

            // Create items and deduct stock
            foreach ($data['items'] as $item) {
                $subtotal     = $item['unit_price'] * $item['quantity'];
                $costSubtotal = $item['cost_price'] * $item['quantity'];
                $totalAmount += $subtotal;
                $totalCost   += $costSubtotal;

                RetailSaleItem::create([
                    'retail_sale_id' => $sale->id,
                    'product_id'     => $item['product_id'],
                    'product_name'   => Product::find($item['product_id'])->name,
                    'unit_name'      => $item['unit_name'],
                    'quantity'       => $item['quantity'],
                    'quantity_base'  => $item['quantity_base'],
                    'unit_price'     => $item['unit_price'],
                    'cost_price'     => $item['cost_price'],
                    'subtotal'       => $subtotal,
                ]);

                Product::where('id', $item['product_id'])->decrement('quantity', $item['quantity_base']);
            }

            $sale->update([
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'profit'       => $totalAmount - $totalCost,
            ]);

            DB::commit();
            return response()->json($sale->load('items'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }
}
