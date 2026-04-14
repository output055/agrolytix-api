<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        return response()->json(User::where('role', 'Worker')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'contact'  => 'nullable|string',
        ]);
        $data['role']     = 'Worker';
        $data['status']   = 'active';
        $data['password'] = Hash::make($data['password']);
        return response()->json(User::create($data), 201);
    }

    public function update(Request $request, User $worker): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $worker->id,
            'password' => 'nullable|min:6',
            'contact'  => 'nullable|string',
        ]);
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $worker->update($data);
        return response()->json($worker->fresh());
    }

    public function updateStatus(Request $request, User $worker): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $worker->update($data);
        return response()->json($worker->fresh());
    }

    public function destroy(Request $request, User $worker): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $worker->delete();
        return response()->json(['message' => 'Worker deleted']);
    }
}
