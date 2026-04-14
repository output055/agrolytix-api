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
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $data = $request->validate([
            'retail_sale_id' => 'required|exists:retail_sales,id',
            'reason'         => 'nullable|string',
        ]);

        $sale = RetailSale::with('items')->findOrFail($data['retail_sale_id']);

        if ($sale->status === 'reversed') {
            return response()->json(['message' => 'This sale has already been reversed.'], 422);
        }

        DB::beginTransaction();
        try {
            // Restore stock for each item
            foreach ($sale->items as $item) {
                \App\Models\Product::where('id', $item->product_id)
                    ->increment('quantity', $item->quantity_base);
            }

            // Create reversal record
            $reversal = Reversal::create([
                'retail_sale_id'  => $sale->id,
                'user_id'         => $request->user()->id,
                'reason'          => $data['reason'] ?? null,
                'amount_reversed' => $sale->total_amount,
            ]);

            // Mark sale as reversed
            $sale->update(['status' => 'reversed']);

            DB::commit();
            return response()->json($reversal->load(['sale', 'reversedBy']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Reversal failed: ' . $e->getMessage()], 500);
        }
    }
}
