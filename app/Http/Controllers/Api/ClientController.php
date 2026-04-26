<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Client::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'contact'  => 'nullable|string',
            'location' => 'nullable|string',
            'email'    => 'nullable|email',
        ]);
        return response()->json(Client::create($data), 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($client->load('wholesaleSales'));
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'contact'  => 'nullable|string',
            'location' => 'nullable|string',
            'email'    => 'nullable|email',
        ]);
        $client->update($data);
        return response()->json($client);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $client->delete();
        return response()->json(['message' => 'Client deleted']);
    }
}
