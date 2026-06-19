<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailSale;
use App\Models\WholesaleSale;
use App\Models\RetailSaleItem;
use App\Models\WholesaleSaleItem;
use App\Models\Client;
use App\Models\Product;
use App\Models\WholesaleProduct;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    // ─── Existing endpoint (preserved) ───────────────────────────────────────
    public function financial(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->input('date_to', now()->toDateString());
        $businessId = $request->user()->business_id;

        $retail = RetailSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit, SUM(total_amount - profit) as cost')
            ->first();

        $wholesale = WholesaleSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit, SUM(total_amount - profit) as cost, SUM(debt) as outstanding_debt')
            ->first();

        $reversals = \App\Models\Reversal::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('COUNT(*) as count, SUM(amount_reversed) as amount, SUM(cost_reversed) as cost')
            ->first();

        $totalDebt = Client::where('business_id', $businessId)->sum('total_debt');

        $expenses = Expense::whereBetween('expense_date', [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(amount) as total')
            ->first();

        $expensesByCategory = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $lowStock = Product::where('business_id', $businessId)
            ->whereRaw('quantity <= low_stock_alert')
            ->select('id', 'name', 'quantity', 'low_stock_alert', 'base_unit')
            ->get();

        return response()->json([
            'period'                 => ['from' => $from, 'to' => $to],
            'retail'                 => $retail,
            'wholesale'              => $wholesale,
            'reversals'              => $reversals,
            'total_outstanding_debt' => $totalDebt,
            'low_stock_products'     => $lowStock,
            'expenses'               => [
                'total'       => $expenses->total ?? 0,
                'count'       => $expenses->count ?? 0,
                'by_category' => $expensesByCategory,
            ],
        ]);
    }

    // ─── Revenue Report ───────────────────────────────────────────────────────
    public function revenueReport(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->input('date_to', now()->toDateString());
        $businessId = $request->user()->business_id;

        $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        $useMonth = $daysDiff > 60;
        $groupExpr = $useMonth ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'DATE(created_at)';

        $retail = RetailSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit')
            ->first();

        $wholesale = WholesaleSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(profit) as profit')
            ->first();

        $totalExpenses = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])->sum('amount');

        $totalRevenue = floatval($retail->revenue ?? 0) + floatval($wholesale->revenue ?? 0);
        $totalProfit  = floatval($retail->profit ?? 0)  + floatval($wholesale->profit ?? 0);

        $retailTrend = RetailSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->selectRaw("$groupExpr as period, SUM(total_amount) as revenue, SUM(profit) as profit")
            ->groupByRaw($groupExpr)->orderBy('period')->get()->keyBy('period');

        $wholesaleTrend = WholesaleSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw("$groupExpr as period, SUM(total_amount) as revenue, SUM(profit) as profit")
            ->groupByRaw($groupExpr)->orderBy('period')->get()->keyBy('period');

        $expExpr = $useMonth ? 'DATE_FORMAT(expense_date, "%Y-%m")' : 'expense_date';
        $expTrend = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw("$expExpr as period, SUM(amount) as total")
            ->groupByRaw($expExpr)->orderBy('period')->get()->keyBy('period');

        $allPeriods = collect(array_merge(
            $retailTrend->keys()->toArray(),
            $wholesaleTrend->keys()->toArray(),
            $expTrend->keys()->toArray()
        ))->unique()->sort()->values();

        return response()->json([
            'summary' => [
                'total_revenue'     => $totalRevenue,
                'total_profit'      => $totalProfit,
                'total_expenses'    => floatval($totalExpenses),
                'net_profit'        => $totalProfit - floatval($totalExpenses),
                'retail_revenue'    => floatval($retail->revenue ?? 0),
                'retail_profit'     => floatval($retail->profit ?? 0),
                'retail_count'      => intval($retail->count ?? 0),
                'wholesale_revenue' => floatval($wholesale->revenue ?? 0),
                'wholesale_profit'  => floatval($wholesale->profit ?? 0),
                'wholesale_count'   => intval($wholesale->count ?? 0),
            ],
            'charts' => [
                'trend' => [
                    'labels'   => $allPeriods->toArray(),
                    'datasets' => [
                        ['label' => 'Retail Revenue',    'data' => $allPeriods->map(fn($p) => floatval($retailTrend->get($p)?->revenue    ?? 0))->toArray()],
                        ['label' => 'Wholesale Revenue', 'data' => $allPeriods->map(fn($p) => floatval($wholesaleTrend->get($p)?->revenue  ?? 0))->toArray()],
                        ['label' => 'Expenses',          'data' => $allPeriods->map(fn($p) => floatval($expTrend->get($p)?->total         ?? 0))->toArray()],
                    ],
                ],
            ],
        ]);
    }

    // ─── Sales Insights ───────────────────────────────────────────────────────
    public function salesInsights(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->input('date_to', now()->toDateString());
        $businessId = $request->user()->business_id;

        $topRetail = RetailSaleItem::join('retail_sales', 'retail_sale_items.retail_sale_id', '=', 'retail_sales.id')
            ->where('retail_sales.business_id', $businessId)
            ->whereBetween('retail_sales.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('retail_sales.status', 'completed')
            ->selectRaw('product_name, SUM(quantity_base) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_name')->orderByDesc('total_revenue')->limit(10)->get();

        $topWholesale = WholesaleSaleItem::join('wholesale_sales', 'wholesale_sale_items.wholesale_sale_id', '=', 'wholesale_sales.id')
            ->where('wholesale_sales.business_id', $businessId)
            ->whereBetween('wholesale_sales.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('wholesale_sales.status', '!=', 'reversed')
            ->selectRaw('product_name, SUM(quantity_base) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_name')->orderByDesc('total_revenue')->limit(10)->get();

        $daysDiff  = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        $groupExpr = $daysDiff > 60 ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'DATE(created_at)';

        $retailTrend = RetailSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->selectRaw("$groupExpr as period, COUNT(*) as count, SUM(total_amount) as revenue")
            ->groupByRaw($groupExpr)->orderBy('period')->get()->keyBy('period');

        $wholesaleTrend = WholesaleSale::where('business_id', $businessId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'reversed')
            ->selectRaw("$groupExpr as period, COUNT(*) as count, SUM(total_amount) as revenue")
            ->groupByRaw($groupExpr)->orderBy('period')->get()->keyBy('period');

        $allPeriods = collect(array_merge(
            $retailTrend->keys()->toArray(),
            $wholesaleTrend->keys()->toArray()
        ))->unique()->sort()->values();

        return response()->json([
            'summary' => [
                'top_retail_product'    => $topRetail->first()?->product_name,
                'top_wholesale_product' => $topWholesale->first()?->product_name,
                'top_retail_revenue'    => floatval($topRetail->first()?->total_revenue ?? 0),
                'top_wholesale_revenue' => floatval($topWholesale->first()?->total_revenue ?? 0),
            ],
            'charts' => [
                'sales_trend' => [
                    'labels'   => $allPeriods->toArray(),
                    'datasets' => [
                        ['label' => 'Retail',    'data' => $allPeriods->map(fn($p) => intval($retailTrend->get($p)?->count    ?? 0))->toArray()],
                        ['label' => 'Wholesale', 'data' => $allPeriods->map(fn($p) => intval($wholesaleTrend->get($p)?->count ?? 0))->toArray()],
                    ],
                ],
                'top_retail' => [
                    'labels' => $topRetail->pluck('product_name')->toArray(),
                    'data'   => $topRetail->pluck('total_revenue')->map(fn($v) => floatval($v))->toArray(),
                ],
                'top_wholesale' => [
                    'labels' => $topWholesale->pluck('product_name')->toArray(),
                    'data'   => $topWholesale->pluck('total_revenue')->map(fn($v) => floatval($v))->toArray(),
                ],
            ],
            'tables' => [
                'top_retail'    => $topRetail->map(fn($r) => ['name' => $r->product_name, 'qty' => floatval($r->total_qty), 'revenue' => floatval($r->total_revenue)])->toArray(),
                'top_wholesale' => $topWholesale->map(fn($r) => ['name' => $r->product_name, 'qty' => floatval($r->total_qty), 'revenue' => floatval($r->total_revenue)])->toArray(),
            ],
        ]);
    }

    // ─── Expense Report ───────────────────────────────────────────────────────
    public function expenseReport(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->input('date_to', now()->toDateString());
        $businessId = $request->user()->business_id;

        $total = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])->sum('amount');
        $count = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])->count();

        $byCategory = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')->orderByDesc('total')->get();

        $daysDiff  = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        $groupExpr = $daysDiff > 60 ? 'DATE_FORMAT(expense_date, "%Y-%m")' : 'expense_date';

        $trend = Expense::where('business_id', $businessId)
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw("$groupExpr as period, SUM(amount) as total")
            ->groupByRaw($groupExpr)->orderBy('period')->get();

        return response()->json([
            'summary' => [
                'total'      => floatval($total),
                'count'      => intval($count),
                'categories' => $byCategory->count(),
                'avg_per_day' => $daysDiff > 0 ? round(floatval($total) / ($daysDiff + 1), 2) : floatval($total),
            ],
            'charts' => [
                'by_category' => [
                    'labels' => $byCategory->pluck('category')->toArray(),
                    'data'   => $byCategory->pluck('total')->map(fn($v) => floatval($v))->toArray(),
                ],
                'trend' => [
                    'labels' => $trend->pluck('period')->toArray(),
                    'data'   => $trend->pluck('total')->map(fn($v) => floatval($v))->toArray(),
                ],
            ],
            'tables' => [
                'by_category' => $byCategory->map(fn($r) => [
                    'category' => $r->category,
                    'total'    => floatval($r->total),
                    'count'    => intval($r->count),
                    'percent'  => $total > 0 ? round(floatval($r->total) / floatval($total) * 100, 1) : 0,
                ])->toArray(),
            ],
        ]);
    }

    // ─── Debt Analysis ────────────────────────────────────────────────────────
    public function debtAnalysis(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $businessId = $request->user()->business_id;

        $totalDebt    = Client::where('business_id', $businessId)->sum('total_debt');
        $debtorsCount = Client::where('business_id', $businessId)->where('total_debt', '>', 0)->count();

        $clients = Client::select('id', 'name', 'contact', 'location', 'total_debt')
            ->where('business_id', $businessId)
            ->where('total_debt', '>', 0)
            ->orderByDesc('total_debt')
            ->get();

        $now   = Carbon::now();
        $aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];

        foreach ($clients as $client) {
            $oldest = WholesaleSale::where('business_id', $businessId)
                ->where('client_id', $client->id)
                ->where('debt', '>', 0)
                ->where('status', '!=', 'reversed')
                ->orderBy('created_at')
                ->first();

            $days = $oldest ? $now->diffInDays(Carbon::parse($oldest->created_at)) : 0;

            if ($days <= 30)      $aging['0-30']  += floatval($client->total_debt);
            elseif ($days <= 60)  $aging['31-60'] += floatval($client->total_debt);
            elseif ($days <= 90)  $aging['61-90'] += floatval($client->total_debt);
            else                  $aging['90+']   += floatval($client->total_debt);
        }

        return response()->json([
            'summary' => [
                'total_debt'    => floatval($totalDebt),
                'debtors_count' => intval($debtorsCount),
                'highest_debt'  => floatval($clients->first()?->total_debt ?? 0),
                'top_debtor'    => $clients->first()?->name,
            ],
            'charts' => [
                'aging' => [
                    'labels' => ['0–30 days', '31–60 days', '61–90 days', '90+ days'],
                    'data'   => array_values($aging),
                ],
                'per_client' => [
                    'labels' => $clients->take(8)->pluck('name')->toArray(),
                    'data'   => $clients->take(8)->pluck('total_debt')->map(fn($v) => floatval($v))->toArray(),
                ],
            ],
            'tables' => [
                'clients' => $clients->map(fn($c) => [
                    'id'       => $c->id,
                    'name'     => $c->name,
                    'contact'  => $c->contact,
                    'location' => $c->location,
                    'debt'     => floatval($c->total_debt),
                ])->toArray(),
            ],
        ]);
    }

    // ─── Inventory Insights ───────────────────────────────────────────────────
    public function inventoryInsights(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $businessId = $request->user()->business_id;

        $retailLow  = Product::where('business_id', $businessId)
            ->whereRaw('quantity > 0 AND quantity <= low_stock_alert')
            ->select('id', 'name', 'category', 'quantity', 'low_stock_alert', 'base_unit', 'cost_price')->orderBy('quantity')->get();
        $retailOut  = Product::where('business_id', $businessId)
            ->where('quantity', 0)
            ->select('id', 'name', 'category', 'quantity', 'low_stock_alert', 'base_unit', 'cost_price')->get();
        $wsLow      = WholesaleProduct::where('business_id', $businessId)
            ->whereRaw('quantity > 0 AND quantity <= low_stock_alert')
            ->select('id', 'name', 'category', 'quantity', 'low_stock_alert', 'base_unit', 'cost_price')->orderBy('quantity')->get();
        $wsOut      = WholesaleProduct::where('business_id', $businessId)
            ->where('quantity', 0)
            ->select('id', 'name', 'category', 'quantity', 'low_stock_alert', 'base_unit', 'cost_price')->get();

        $retailCostVal   = floatval(Product::where('business_id', $businessId)
            ->selectRaw('SUM(quantity * cost_price) as v')->value('v') ?? 0);
        $retailSellVal   = floatval(Product::where('business_id', $businessId)
            ->selectRaw('SUM(quantity * sell_price) as v')->value('v') ?? 0);
        $wsCostVal       = floatval(WholesaleProduct::where('business_id', $businessId)
            ->selectRaw('SUM(quantity * cost_price) as v')->value('v') ?? 0);
        $wsSellVal       = floatval(WholesaleProduct::where('business_id', $businessId)
            ->selectRaw('SUM(quantity * sell_price) as v')->value('v') ?? 0);

        $retailByCat = Product::where('business_id', $businessId)
            ->selectRaw('category, COUNT(*) as count, SUM(quantity * cost_price) as value')
            ->groupBy('category')->orderByDesc('value')->get();

        $mapFn = fn($p) => ['id' => $p->id, 'name' => $p->name, 'category' => $p->category, 'quantity' => $p->quantity, 'alert' => $p->low_stock_alert, 'unit' => $p->base_unit, 'cost' => floatval($p->cost_price)];

        return response()->json([
            'summary' => [
                'retail_low_stock'       => $retailLow->count(),
                'retail_out_of_stock'    => $retailOut->count(),
                'wholesale_low_stock'    => $wsLow->count(),
                'wholesale_out_of_stock' => $wsOut->count(),
                'retail_cost_value'      => $retailCostVal,
                'retail_sell_value'      => $retailSellVal,
                'wholesale_cost_value'   => $wsCostVal,
                'wholesale_sell_value'   => $wsSellVal,
                'total_inventory_value'  => $retailCostVal + $wsCostVal,
            ],
            'charts' => [
                'retail_by_category' => [
                    'labels' => $retailByCat->pluck('category')->toArray(),
                    'data'   => $retailByCat->pluck('value')->map(fn($v) => floatval($v))->toArray(),
                ],
            ],
            'tables' => [
                'retail_low'    => $retailLow->map($mapFn)->toArray(),
                'retail_out'    => $retailOut->map($mapFn)->toArray(),
                'wholesale_low' => $wsLow->map($mapFn)->toArray(),
                'wholesale_out' => $wsOut->map($mapFn)->toArray(),
            ],
        ]);
    }
}
