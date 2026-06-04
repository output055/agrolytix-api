<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    private function rootBusinessFor(Request $request): Business
    {
        $business = Business::findOrFail($request->user()->business_id);

        if (!empty($business->parent_id)) {
            abort(403, 'Only the headquarters account can manage branches.');
        }

        return $business;
    }

    /**
     * GET /api/branches
     *
     * List all child branches of the current headquarters business.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $business = $this->rootBusinessFor($request);

        $branches = Business::where('parent_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'address', 'parent_id', 'created_at']);

        return response()->json($branches);
    }

    /**
     * POST /api/branches
     *
     * Create a new branch. Only allowed for root business Admins.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $business = $this->rootBusinessFor($request);

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        $data['parent_id'] = $business->id;

        $branch = Business::create($data);

        \App\Models\AuditLog::create([
            'user_id'     => $request->user()->id,
            'action_type' => 'BRANCH_CREATED',
            'status'      => 'success',
            'severity'    => 'INFO',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => ['branch_name' => $branch->name, 'branch_id' => $branch->id],
            'business_id' => $business->id,
        ]);

        return response()->json($branch, 201);
    }

    /**
     * PUT /api/branches/{branch}
     *
     * Update a child branch. Only allowed for headquarters Admins.
     */
    public function update(Request $request, Business $branch): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $business = $this->rootBusinessFor($request);

        abort_unless((int) $branch->parent_id === (int) $business->id, 404);

        $data = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        $branch->update($data);

        \App\Models\AuditLog::create([
            'user_id'     => $request->user()->id,
            'action_type' => 'BRANCH_UPDATED',
            'status'      => 'success',
            'severity'    => 'INFO',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => ['branch_name' => $branch->name, 'branch_id' => $branch->id],
            'business_id' => $business->id,
        ]);

        return response()->json($branch->fresh(['parent']));
    }

    /**
     * DELETE /api/branches/{branch}
     *
     * Delete a child branch. Only allowed for headquarters Admins.
     */
    public function destroy(Request $request, Business $branch): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Admin access required');

        $business = $this->rootBusinessFor($request);

        abort_unless((int) $branch->parent_id === (int) $business->id, 404);

        $branchName = $branch->name;
        $branchId = $branch->id;

        $branch->delete();

        \App\Models\AuditLog::create([
            'user_id'     => $request->user()->id,
            'action_type' => 'BRANCH_DELETED',
            'status'      => 'success',
            'severity'    => 'INFO',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => ['branch_name' => $branchName, 'branch_id' => $branchId],
            'business_id' => $business->id,
        ]);

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}
