<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Debt;
use App\Models\WholesaleSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WholesaleSaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WholesaleSale::with(['items', 'client', 'worker'])->latest();

        $today = Carbon::today();
        $preset = $request->input('preset');

        switch ($preset) {
            case 'today':
                $query->whereDate('created_at', $today);
                break;
            case 'yesterday':
                $query->whereDate('created_at', $today->copy()->subDay());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [
                    $today->copy()->startOfWeek(),
                    $today->copy()->endOfWeek(),
                ]);
                break;
            case 'this_month':
                $query->whereMonth('created_at', $today->month)
                      ->whereYear('created_at', $today->year);
                break;
            case 'this_year':
                $query->whereYear('created_at', $today->year);
                break;
            case 'custom':
                if ($request->filled('date_from')) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                }
                break;
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('worker_id')) {
            $query->where('user_id', $request->worker_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Summary totals before pagination (reorder() strips ORDER BY to avoid MySQL aggregate error)
        $summary = (clone $query)->reorder()->selectRaw('
            COUNT(*) as total_transactions,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(total_cost), 0) as total_cost,
            COALESCE(SUM(profit), 0) as total_profit,
            COALESCE(SUM(debt), 0) as total_outstanding_debt,
            COALESCE(SUM(amount_paid), 0) as total_collected
        ')->first();

        $paginated = $query->paginate(20);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'summary' => [
                'total_transactions'   => (int)   ($summary->total_transactions   ?? 0),
                'total_revenue'        => (float) ($summary->total_revenue        ?? 0),
                'total_cost'           => (float) ($summary->total_cost           ?? 0),
                'total_profit'         => (float) ($summary->total_profit         ?? 0),
                'total_outstanding_debt' => (float) ($summary->total_outstanding_debt ?? 0),
                'total_collected'      => (float) ($summary->total_collected      ?? 0),
            ],
        ]);
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
                'debt'        => $newDebt,
                'amount_paid' => $sale->amount_paid + $data['amount_paid'],
                'status'      => $newDebt == 0 ? 'completed' : 'partial',
            ]);

            $sale->client->decrement('total_debt', $data['amount_paid']);

            DB::commit();
            return response()->json($sale->fresh(['debtPayments', 'client']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Payment failed: ' . $e->getMessage()], 500);
        }
    }
}
