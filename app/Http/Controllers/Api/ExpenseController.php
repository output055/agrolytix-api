<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    /**
     * List expenses.
     * Admins see all; workers see only their own.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = \App\Models\Expense::with('recorder:id,name');

        // Workers can only see their own expenses
        if (!$user->isAdmin()) {
            $query->where('recorded_by', $user->id);
        }

        // Filters
        if ($from = $request->input('date_from')) {
            $query->where('expense_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->where('expense_date', '<=', $to);
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if ($user->isAdmin() && $workerId = $request->input('worker_id')) {
            $query->where('recorded_by', $workerId);
        }

        $expenses = $query->orderByDesc('expense_date')
                          ->orderByDesc('created_at')
                          ->paginate(20);

        return response()->json($expenses);
    }

    /**
     * Get current day stats for the expenses page.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->getTodayStats());
    }

    private function getTodayStats()
    {
        $today = \Illuminate\Support\Carbon::today();
        $businessId = auth()->user()->business_id;

        $retailRevenue = \App\Models\RetailSale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $retailProfit = \App\Models\RetailSale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('profit');

        $wholesaleRevenue = \App\Models\WholesaleSale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'reversed')
            ->sum('total_amount');

        $wholesaleProfit = \App\Models\WholesaleSale::where('business_id', $businessId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'reversed')
            ->sum('profit');

        $todayExpenses = \App\Models\Expense::where('business_id', $businessId)
            ->whereDate('expense_date', $today)->sum('amount');

        $totalRevenue = $retailRevenue + $wholesaleRevenue;
        $totalProfit  = $retailProfit + $wholesaleProfit;

        return [
            'retail_revenue'    => (float)$retailRevenue,
            'retail_profit'     => (float)$retailProfit,
            'wholesale_revenue' => (float)$wholesaleRevenue,
            'wholesale_profit'  => (float)$wholesaleProfit,
            'total_revenue'     => (float)$totalRevenue,
            'total_profit'      => (float)$totalProfit,
            'today_expenses'    => (float)$todayExpenses,
            'net_revenue'       => (float)($totalRevenue - $todayExpenses),
            'net_profit'        => (float)($totalProfit - $todayExpenses),
            'date'              => $today->toDateString(),
        ];
    }

    /**
     * Create a new expense.
     * Force today's date and validate against revenue.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'amount'   => 'required|numeric|min:0.01',
            'category' => 'required|in:salary,fuel,rent,utilities,maintenance,food,cleaning,other',
            'note'     => 'nullable|string|max:1000',
        ]);

        $stats = $this->getTodayStats();

        if ($data['amount'] > $stats['net_revenue']) {
            return response()->json([
                'message' => 'Insufficient funds. Total revenue for today is GH₵' . number_format($stats['total_revenue'], 2) .
                             ', and remaining balance is GH₵' . number_format($stats['net_revenue'], 2) . '.'
            ], 422);
        }

        $data['expense_date'] = $stats['date'];
        $data['recorded_by']  = $request->user()->id;

        $expense = \App\Models\Expense::create($data);
        $expense->load('recorder:id,name');

        return response()->json($expense, 201);
    }

    /**
     * Delete an expense (Admin only).
     */
    public function destroy(Request $request, \App\Models\Expense $expense): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only admins can delete expenses.');

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }
}
