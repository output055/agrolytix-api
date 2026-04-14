<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ['error' => 'Invalid credentials', 'status' => 401];
        }

        if ($user->status !== 'active') {
            return ['error' => 'Account is inactive. Please contact the admin.', 'status' => 403];
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('agrolytix-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
