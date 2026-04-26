<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailSale;
use App\Models\Reversal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReversalController extends Controller
{
    public function index(): JsonResponse
    {
        $reversals = Reversal::with(['sale.items', 'reversedBy'])->latest()->get();
        return response()->json($reversals);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'retail_sale_id'   => 'required|exists:retail_sales,id',
            'reason'           => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $sale = RetailSale::with('items')->findOrFail($data['retail_sale_id']);

        // Enforce 24-hour reversal limit
        if ($sale->created_at->diffInHours(now()) >= 24) {
            return response()->json(['message' => 'Reversal period has expired'], 422);
        }

        if ($sale->status === 'reversed') {
            return response()->json(['message' => 'This sale has already been reversed.'], 422);
        }

        // Key items requested by ID for quick lookup
        $requestedItems = collect($data['items'])->keyBy('id');
        $selectedItems = $sale->items->whereIn('id', $requestedItems->keys());

        if ($selectedItems->isEmpty()) {
            return response()->json(['message' => 'No valid items selected for reversal.'], 422);
        }

        $amountReversed = 0;
        $costReversed = 0;
        $reversedItemsData = [];

        DB::beginTransaction();
        try {
            foreach ($selectedItems as $item) {
                $reqQty = $requestedItems[$item->id]['quantity'];

                // Enforce constraint that requested reversal qty cannot exceed remaining quantity
                if ($reqQty > $item->quantity) {
                    throw new \Exception("Cannot reverse {$reqQty} of {$item->product_name}. Only {$item->quantity} remains.");
                }

                // Calculate base quantity to return to stock proportionately
                $ratio = $reqQty / $item->quantity;
                $qtyBaseToRestore = round($item->quantity_base * $ratio);

                // Reversal values for this specific iteration
                $iterationAmount = $reqQty * $item->unit_price;
                $iterationCost   = $reqQty * $item->cost_price;

                $amountReversed += $iterationAmount;
                $costReversed   += $iterationCost;

                // 1. Restore stock
                \App\Models\Product::where('id', $item->product_id)
                    ->increment('quantity', $qtyBaseToRestore);

                // 2. Build snapshot for reversals table
                $reversedItemsData[] = [
                    'item_id'       => $item->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product_name,
                    'unit_name'     => $item->unit_name,
                    'quantity'      => $reqQty,
                    'quantity_base' => $qtyBaseToRestore,
                    'unit_price'    => $item->unit_price,
                    'cost_price'    => $item->cost_price,
                    'subtotal'      => $iterationAmount,
                ];

                // 3. Update the retail_sale_items row so receipts strictly tally
                $item->update([
                    'quantity'      => $item->quantity - $reqQty,
                    'quantity_base' => $item->quantity_base - $qtyBaseToRestore,
                    'subtotal'      => $item->subtotal - $iterationAmount,
                ]);
            }

            // After adjusting quantities, if all items in the sale are mathematically 0, it's a full reversal.
            $sale->refresh(); // Load updated items
            $isPartial = $sale->items->sum('quantity') > 0;

            // Create reversal record
            $reversal = Reversal::create([
                'retail_sale_id'  => $sale->id,
                'user_id'         => $request->user()->id,
                'reason'          => $data['reason'] ?? null,
                'reversed_items'  => $reversedItemsData,
                'amount_reversed' => $amountReversed,
                'cost_reversed'   => $costReversed,
                'is_partial'      => $isPartial,
            ]);

            // Update the sale financials
            $sale->update([
                'total_amount' => $sale->total_amount - $amountReversed,
                'total_cost'   => $sale->total_cost - $costReversed,
                'profit'       => ($sale->total_amount - $amountReversed) - ($sale->total_cost - $costReversed),
                'status'       => $isPartial ? 'partial_reversal' : 'reversed',
            ]);

            DB::commit();
            return response()->json($reversal->load(['sale.items', 'reversedBy']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Reversal failed: ' . $e->getMessage()], 500);
        }
    }
}
