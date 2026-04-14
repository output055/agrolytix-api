<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WholesaleProduct;
use App\Models\WholesaleSale;
use App\Models\WholesaleSaleItem;
use App\Models\ClientSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WholesalePosController extends Controller
{
    /**
     * Process wholesale checkout.
     * Expects: { client_id, amount_paid, items: [...] }
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'                 => 'required|exists:clients,id',
            'amount_paid'               => 'required|numeric|min:0',
            'items'                     => 'required|array|min:1',
            'items.*.wholesale_product_id' => 'required|exists:wholesale_products,id',
            'items.*.unit_name'         => 'required|string',
            'items.*.quantity'          => 'required|integer|min:1',
            'items.*.quantity_base'     => 'required|integer|min:1',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'items.*.cost_price'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Validate stock
            foreach ($data['items'] as $item) {
                $product = WholesaleProduct::findOrFail($item['wholesale_product_id']);
                if ($product->quantity < $item['quantity_base']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Insufficient stock for '{$product->name}'. Available: {$product->quantity} {$product->base_unit}(s)."
                    ], 422);
                }
            }

            $totalAmount = 0;
            $totalCost   = 0;

            $sale = WholesaleSale::create([
                'user_id'        => $request->user()->id,
                'client_id'      => $data['client_id'],
                'receipt_number' => 'WHL-' . strtoupper(Str::random(8)),
                'total_amount'   => 0,
                'total_cost'     => 0,
                'profit'         => 0,
                'amount_paid'    => $data['amount_paid'],
                'debt'           => 0,
                'status'         => 'completed',
            ]);

            foreach ($data['items'] as $item) {
                $subtotal     = $item['unit_price'] * $item['quantity'];
                $costSubtotal = $item['cost_price'] * $item['quantity'];
                $totalAmount += $subtotal;
                $totalCost   += $costSubtotal;

                WholesaleSaleItem::create([
                    'wholesale_sale_id'    => $sale->id,
                    'wholesale_product_id' => $item['wholesale_product_id'],
                    'product_name'         => WholesaleProduct::find($item['wholesale_product_id'])->name,
                    'unit_name'            => $item['unit_name'],
                    'quantity'             => $item['quantity'],
                    'quantity_base'        => $item['quantity_base'],
                    'unit_price'           => $item['unit_price'],
                    'cost_price'           => $item['cost_price'],
                    'subtotal'             => $subtotal,
                ]);

                WholesaleProduct::where('id', $item['wholesale_product_id'])
                    ->decrement('quantity', $item['quantity_base']);
            }

            $debt = max(0, $totalAmount - $data['amount_paid']);
            $status = $debt > 0 ? 'partial' : 'completed';

            $sale->update([
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'profit'       => $totalAmount - $totalCost,
                'debt'         => $debt,
                'status'       => $status,
            ]);

            // Update client total_debt
            $client = Client::find($data['client_id']);
            $client->increment('total_debt', $debt);

            DB::commit();
            return response()->json($sale->load(['items', 'client']), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }
}
