<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && empty($request->user()->business_id)) {
            return response()->json([
                'message' => 'Unauthorized. No active business context found for this account.'
            ], 403);
        }

        return $next($request);
    }
}
