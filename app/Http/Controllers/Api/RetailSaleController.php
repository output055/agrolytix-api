<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RetailSaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RetailSale::with(['items', 'worker'])->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $sale = RetailSale::with(['items', 'worker', 'reversal'])->findOrFail($id);
        return response()->json($sale);
    }
}
