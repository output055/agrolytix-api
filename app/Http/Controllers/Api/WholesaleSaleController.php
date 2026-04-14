<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Debt;
use App\Models\WholesaleSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WholesaleSaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WholesaleSale::with(['items', 'client', 'worker'])->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $sale = WholesaleSale::with(['items', 'client', 'worker', 'debtPayments'])->findOrFail($id);
        return response()->json($sale);
    }

    public function payDebt(Request $request, int $id): JsonResponse
    {
        $sale = WholesaleSale::findOrFail($id);
        $data = $request->validate([
            'amount_paid' => 'required|numeric|min:0.01|max:' . $sale->debt,
            'note'        => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $oldDebt = $sale->debt;
            $newDebt = max(0, $oldDebt - $data['amount_paid']);

            Debt::create([
                'wholesale_sale_id' => $sale->id,
                'client_id'         => $sale->client_id,
                'amount_paid'       => $data['amount_paid'],
                'old_debt'          => $oldDebt,
                'new_debt'          => $newDebt,
                'note'              => $data['note'] ?? null,
            ]);

            $sale->update([
                'debt'   => $newDebt,
                'amount_paid' => $sale->amount_paid + $data['amount_paid'],
                'status' => $newDebt == 0 ? 'completed' : 'partial',
            ]);

            // Update client aggregate debt
            $sale->client->decrement('total_debt', $data['amount_paid']);

            DB::commit();
            return response()->json($sale->fresh(['debtPayments', 'client']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Payment failed: ' . $e->getMessage()], 500);
        }
    }
}
