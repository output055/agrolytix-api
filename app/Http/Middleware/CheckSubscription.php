<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Load business if not already loaded
        $business = $user->relationLoaded('business')
            ? $user->business
            : $user->load('business')->business;

        if (!$business) {
            return response()->json(['message' => 'No business associated with your account.'], 403);
        }

        if (!$business->isAccessAllowed()) {
            return response()->json([
                'message'             => 'Your trial has expired or your subscription is inactive. Please subscribe to continue.',
                'subscription_status' => $business->subscription_status,
                'trial_ends_at'       => $business->trial_ends_at,
                'requires_payment'    => true,
            ], 402);
        }

        return $next($request);
    }
}
