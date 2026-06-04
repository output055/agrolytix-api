<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\WholesaleProduct;
use App\Models\StockTransfer;
use App\Models\Scopes\BusinessScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * GET /api/stock-transfers/eligible-businesses
     *
     * Returns list of sibling/parent/child businesses eligible for cross-branch transfers.
     */
    public function eligibleBusinesses(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $currentBusiness = Business::findOrFail($request->user()->business_id);
        $siblings = $currentBusiness->getSiblingBusinesses();

        return response()->json($siblings);
    }

    /**
     * GET /api/stock-transfers/eligible-products
     *
     * Returns products from a target business (sibling/branch) for dropdown selection.
     * Query params: target_business_id (int), type (retail|wholesale)
     */
    public function eligibleProducts(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $data = $request->validate([
            'target_business_id' => 'required|integer|exists:businesses,id',
            'type'               => 'required|in:retail,wholesale',
        ]);

        // Verify the target business is actually in the same family
        $currentBusiness = Business::findOrFail($request->user()->business_id);
        $eligible = $currentBusiness->getSiblingBusinesses()->pluck('id');

        if (!$eligible->contains((int) $data['target_business_id'])) {
            abort(403, 'Target business is not in your business group.');
        }

        $model = $data['type'] === 'retail' ? Product::class : WholesaleProduct::class;

        // Bypass the BusinessScope to query a different business's products
        $products = $model::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $data['target_business_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'base_unit', 'category']);

        return response()->json($products);
    }

    /**
     * POST /api/stock-transfers
     *
     * Atomically move stock from one side (retail/wholesale) to the other.
     * Supports both internal (same business) and cross-branch (different business) transfers.
     * If `to_product_id` is null the destination product is auto-created.
     */
    public function transfer(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $data = $request->validate([
            'from_type'          => 'required|in:retail,wholesale',
            'from_product_id'    => 'required|integer|min:1',
            'to_type'            => 'required|in:retail,wholesale',
            'to_product_id'      => 'nullable|integer|min:1',
            'to_business_id'     => 'nullable|integer|exists:businesses,id',
            'quantity'           => 'required|integer|min:1',
            'note'               => 'nullable|string|max:500',
        ]);

        // For internal transfers, from_type and to_type must differ
        $isCrossBranch = !empty($data['to_business_id'])
            && (int) $data['to_business_id'] !== $request->user()->business_id;

        if (!$isCrossBranch && $data['from_type'] === $data['to_type']) {
            abort(422, 'Source and destination store type must differ for internal transfers.');
        }

        // Cross-branch: validate the target business is in the same family
        if ($isCrossBranch) {
            $currentBusiness = Business::findOrFail($request->user()->business_id);
            $eligible = $currentBusiness->getSiblingBusinesses()->pluck('id');

            if (!$eligible->contains((int) $data['to_business_id'])) {
                abort(403, 'Target business is not in your business group.');
            }
        }

        $result = DB::transaction(function () use ($data, $request, $isCrossBranch) {

            // --- Load & lock source product (always scoped to current business) ---
            $sourceModel = $data['from_type'] === 'retail' ? Product::class : WholesaleProduct::class;
            $source = $sourceModel::lockForUpdate()->findOrFail($data['from_product_id']);

            // Hard-block: ensure enough stock
            if ($source->quantity < $data['quantity']) {
                abort(422, "Insufficient stock. Available: {$source->quantity} {$source->base_unit}(s).");
            }

            // Decrement source
            $source->quantity -= $data['quantity'];
            $source->save();

            // --- Load or auto-create destination product ---
            $destModel   = $data['to_type'] === 'retail' ? Product::class : WholesaleProduct::class;
            $autoCreated = false;
            $toBusinessId = $isCrossBranch ? (int) $data['to_business_id'] : $source->business_id;

            if (!empty($data['to_product_id'])) {
                // Find in the target business (bypass scope for cross-branch)
                $dest = $destModel::withoutGlobalScope(BusinessScope::class)
                    ->where('business_id', $toBusinessId)
                    ->lockForUpdate()
                    ->find($data['to_product_id']);
            } else {
                $dest = null;
            }

            if ($dest) {
                // Existing destination product — just add stock
                $dest->quantity += $data['quantity'];
                $dest->save();
            } else {
                // Auto-create destination product in target business
                // withoutGlobalScope so the create lands in the right business
                $dest = $destModel::withoutGlobalScopes()->forceCreate([
                    'name'            => $source->name,
                    'category'        => $source->category,
                    'description'     => $source->description,
                    'cost_price'      => $source->cost_price,
                    'sell_price'      => $source->sell_price,
                    'base_unit'       => $source->base_unit,
                    'quantity'        => $data['quantity'],
                    'low_stock_alert' => $source->low_stock_alert,
                    'business_id'     => $toBusinessId,
                ]);
                $autoCreated = true;
            }

            // --- Create audit record ---
            $transfer = StockTransfer::create([
                'business_id'       => $source->business_id,
                'from_type'         => $data['from_type'],
                'from_product_id'   => $source->id,
                'from_product_name' => $source->name,
                'to_type'           => $data['to_type'],
                'to_product_id'     => $dest->id,
                'to_product_name'   => $dest->name,
                'to_business_id'    => $isCrossBranch ? $toBusinessId : null,
                'auto_created'      => $autoCreated,
                'quantity'          => $data['quantity'],
                'note'              => $data['note'] ?? null,
                'transferred_by'    => $request->user()->id,
            ]);

            return [
                'transfer'     => $transfer->load('transferredBy', 'toBusiness'),
                'source'       => $source->fresh('units'),
                'destination'  => $dest->fresh('units'),
                'auto_created' => $autoCreated,
            ];
        });

        return response()->json($result, 201);
    }

    /**
     * GET /api/stock-transfers
     *
     * Paginated transfer history for this business (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $perPage = $request->input('per_page', 15);

        $transfers = StockTransfer::withoutGlobalScope(BusinessScope::class)
            ->with('transferredBy', 'toBusiness', 'business')
            ->where(function ($query) use ($request) {
                $query->where('business_id', $request->user()->business_id)
                      ->orWhere('to_business_id', $request->user()->business_id);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($transfers);
    }
}
