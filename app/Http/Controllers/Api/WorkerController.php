<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\Scopes\BusinessScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        
        $familyIds = Business::findOrFail($request->user()->business_id)->getFamilyBusinessIds();
        
        $workers = User::withoutGlobalScope(BusinessScope::class)
            ->with('business')
            ->whereIn('role', ['Worker', 'Admin'])
            ->where('id', '!=', $request->user()->id) // exclude self
            ->whereIn('business_id', $familyIds)
            ->orderBy('name')
            ->get();
            
        return response()->json($workers);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        
        $adminBusiness = Business::findOrFail($request->user()->business_id);
        $familyIds = $adminBusiness->getFamilyBusinessIds();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6',
            'contact'     => 'nullable|string',
            'role'        => 'nullable|in:Admin,Worker',
            'permissions' => 'nullable|array',
            'business_id' => 'nullable|integer',
        ]);
        
        if (!empty($data['business_id'])) {
            if (!in_array((int) $data['business_id'], $familyIds)) {
                abort(403, 'Unauthorized business assignment');
            }
        } else {
            $data['business_id'] = $request->user()->business_id;
        }

        $data['role']        = $data['role'] ?? 'Worker';
        $data['status']      = 'active';
        $data['password']    = Hash::make($data['password']);
        // Admins get all permissions by default (handled on frontend)
        $data['permissions'] = ($data['role'] === 'Admin') ? [] : ($data['permissions'] ?? []);

        $worker = User::create($data);
        return response()->json($worker->load('business'), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        
        $adminBusiness = Business::findOrFail($request->user()->business_id);
        $familyIds = $adminBusiness->getFamilyBusinessIds();
        
        $worker = User::withoutGlobalScope(BusinessScope::class)
            ->whereIn('business_id', $familyIds)
            ->whereIn('role', ['Worker', 'Admin'])
            ->where('id', '!=', $request->user()->id) // cannot edit self here
            ->findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'email'       => 'sometimes|email|unique:users,email,' . $worker->id,
            'password'    => 'nullable|min:6',
            'contact'     => 'nullable|string',
            'role'        => 'nullable|in:Admin,Worker',
            'permissions' => 'nullable|array',
            'business_id' => 'sometimes|integer',
        ]);
        
        if (!empty($data['business_id'])) {
            if (!in_array((int) $data['business_id'], $familyIds)) {
                abort(403, 'Unauthorized business assignment');
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if (!isset($data['permissions']) && $request->has('permissions')) {
            $data['permissions'] = [];
        }
        
        $worker->update($data);
        return response()->json($worker->load('business')->fresh());
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        
        $adminBusiness = Business::findOrFail($request->user()->business_id);
        $familyIds = $adminBusiness->getFamilyBusinessIds();
        
        $worker = User::withoutGlobalScope(BusinessScope::class)
            ->whereIn('business_id', $familyIds)
            ->whereIn('role', ['Worker', 'Admin'])
            ->where('id', '!=', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $worker->update($data);
        return response()->json($worker->load('business')->fresh());
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        
        $adminBusiness = Business::findOrFail($request->user()->business_id);
        $familyIds = $adminBusiness->getFamilyBusinessIds();
        
        $worker = User::withoutGlobalScope(BusinessScope::class)
            ->whereIn('business_id', $familyIds)
            ->whereIn('role', ['Worker', 'Admin'])
            ->where('id', '!=', $request->user()->id)
            ->findOrFail($id);

        $worker->delete();
        return response()->json(['message' => 'Worker deleted']);
    }
}
