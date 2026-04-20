<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WholesaleProduct;
use App\Models\RetailSale;
use App\Models\WholesaleSale;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $today = Carbon::today();

        $retailRevenue = RetailSale::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $retailProfit = RetailSale::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('profit');

        $wholesaleRevenue = WholesaleSale::whereDate('created_at', $today)
            ->where('status', '!=', 'reversed')
            ->sum('total_amount');

        $wholesaleProfit = WholesaleSale::whereDate('created_at', $today)
            ->where('status', '!=', 'reversed')
            ->sum('profit');

        $totalDebt = Client::sum('total_debt');

        $lowStockCount = Product::whereRaw('quantity <= low_stock_alert')->count() + 
                         WholesaleProduct::whereRaw('quantity <= low_stock_alert')->count();

        $retailAttention = Product::whereRaw('quantity <= low_stock_alert')
            ->select('id', 'name', 'quantity', 'low_stock_alert', 'base_unit')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), ['type' => 'Retail']));

        $wholesaleAttention = WholesaleProduct::whereRaw('quantity <= low_stock_alert')
            ->select('id', 'name', 'quantity', 'low_stock_alert', 'base_unit')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), ['type' => 'Wholesale']));

        $needsAttention = $retailAttention->concat($wholesaleAttention)
            ->sortBy('quantity')
            ->take(10)
            ->values();

        return response()->json([
            'retail_revenue'    => $retailRevenue,
            'retail_profit'     => $retailProfit,
            'wholesale_revenue' => $wholesaleRevenue,
            'wholesale_profit'  => $wholesaleProfit,
            'total_debt'        => $totalDebt,
            'low_stock_count'   => $lowStockCount,
            'needs_attention'   => $needsAttention,
            'date'              => $today->toDateString(),
        ]);
    }
}
