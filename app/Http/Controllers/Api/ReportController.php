<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailSale;
use App\Models\WholesaleSale;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->input('date_to', now()->toDateString());

        $retail = RetailSale::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit, SUM(total_amount - profit) as cost')
            ->first();

        $wholesale = WholesaleSale::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit, SUM(total_amount - profit) as cost, SUM(debt) as outstanding_debt')
            ->first();

        $reversals = \App\Models\Reversal::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('COUNT(*) as count, SUM(amount_reversed) as amount, SUM(cost_reversed) as cost')
            ->first();

        $totalDebt = Client::sum('total_debt');

        $lowStock = Product::whereRaw('quantity <= low_stock_alert')
            ->select('id', 'name', 'quantity', 'low_stock_alert', 'base_unit')
            ->get();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'retail' => $retail,
            'wholesale' => $wholesale,
            'reversals' => $reversals,
            'total_outstanding_debt' => $totalDebt,
            'low_stock_products' => $lowStock,
        ]);
    }
}
