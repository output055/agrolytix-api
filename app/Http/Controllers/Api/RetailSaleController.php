<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class RetailSaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RetailSale::with(['items', 'worker', 'reversal'])->latest();

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

        if ($request->filled('worker_id')) {
            $query->where('user_id', $request->worker_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Clone for summary totals before pagination (reorder() strips ORDER BY to avoid MySQL aggregate error)
        $summary = (clone $query)->reorder()->selectRaw('
            COUNT(*) as total_transactions,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(total_cost), 0) as total_cost,
            COALESCE(SUM(profit), 0) as total_profit
        ')->first();

        $paginated = $query->paginate(20);
        $data = $paginated->items();
        $summaryData = [
            'total_transactions' => (int)   ($summary->total_transactions ?? 0),
            'total_revenue'      => (float) ($summary->total_revenue      ?? 0),
            'total_cost'         => (float) ($summary->total_cost         ?? 0),
            'total_profit'       => (float) ($summary->total_profit       ?? 0),
        ];

        if (!$this->canViewProfit()) {
            $data = $this->hideProfitFields($data);
            $summaryData = $this->hideProfitFields($summaryData);
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'summary' => $summaryData,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $sale = RetailSale::with(['items', 'worker', 'reversal'])->findOrFail($id);
        return response()->json($this->canViewProfit() ? $sale : $this->hideProfitFields($sale));
    }
}
