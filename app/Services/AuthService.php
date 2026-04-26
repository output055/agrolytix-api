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

        if (empty($user->business_id)) {
            \App\Models\AuditLog::create([
                'action_type' => 'LOGIN_FAILED',
                'status'      => 'failure',
                'severity'    => 'WARNING',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'metadata'    => ['email' => $credentials['email'], 'reason' => 'No business assigned']
            ]);
            return ['error' => 'Your account is not assigned to a business.', 'status' => 403];
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

        // Eager load the business details so the frontend has them immediately
        $user->load('business');

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

    public function register(array $data): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $business = \App\Models\Business::create([
                'name' => $data['business_name'],
                'email' => $data['business_email'] ?? null,
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'business_id' => $business->id,
            ]);

            \App\Models\AuditLog::create([
                'user_id'     => $user->id,
                'action_type' => 'BUSINESS_REGISTERED',
                'status'      => 'success',
                'severity'    => 'INFO',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'metadata'    => ['business_name' => $business->name],
                'business_id' => $business->id,
            ]);

            $token = $user->createToken('agrolytix-token')->plainTextToken;
            $user->load('business');

            return [
                'user' => $user,
                'business' => $business,
                'token' => $token,
            ];
        });
    }
}
