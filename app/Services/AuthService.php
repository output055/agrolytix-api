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
            \App\Models\AuditLog::create([
                'action_type' => 'LOGIN_FAILED',
                'status'      => 'failure',
                'severity'    => 'WARNING',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'metadata'    => ['email' => $credentials['email'], 'reason' => 'Invalid credentials']
            ]);
            return ['error' => 'Invalid credentials', 'status' => 401];
        }

        if ($user->status !== 'active') {
            \App\Models\AuditLog::create([
                'user_id'     => $user->id,
                'action_type' => 'LOGIN_FAILED',
                'status'      => 'failure',
                'severity'    => 'WARNING',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'metadata'    => ['email' => $credentials['email'], 'reason' => 'Inactive account']
            ]);
            return ['error' => 'Account is inactive. Please contact the admin.', 'status' => 403];
        }

        $user->update(['last_login_at' => now()]);

        \App\Models\AuditLog::create([
            'user_id'     => $user->id,
            'action_type' => 'LOGIN_SUCCESS',
            'status'      => 'success',
            'severity'    => 'INFO',
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);

        $token = $user->createToken('agrolytix-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        \App\Models\AuditLog::create([
            'user_id'     => $user->id,
            'action_type' => 'LOGOUT',
            'status'      => 'success',
            'severity'    => 'INFO',
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
        $user->currentAccessToken()->delete();
    }
}
