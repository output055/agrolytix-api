<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SuperAdminController extends Controller
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('services.paystack.secret_key', '');
    }

    /** GET /api/super-admin/stats */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $businesses = Business::withoutGlobalScopes()->get();

        $total        = $businesses->count();
        $active       = $businesses->where('subscription_status', 'active')->count();
        $trialing     = $businesses->where('subscription_status', 'trialing')->count();
        $expired      = $businesses->whereIn('subscription_status', ['past_due', 'cancelled'])->count();
        $newThisMonth = Business::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count();

        // Estimated MRR in GHS
        $monthlyMrr = $businesses->where('subscription_status', 'active')->where('subscription_plan', 'monthly')->count() * 200;
        $annualMrr  = $businesses->where('subscription_status', 'active')->where('subscription_plan', 'annual')->count() * round(2500 / 12, 2);
        $mrr        = round($monthlyMrr + $annualMrr, 2);

        // Expiring within 7 days
        $expiringSoon = Business::withoutGlobalScopes()
            ->where('subscription_status', 'active')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
            ->with(['users' => fn($q) => $q->where('role', 'Admin')->limit(1)])
            ->get()
            ->map(fn($b) => [
                'id'                   => $b->id,
                'name'                 => $b->name,
                'owner_email'          => $b->users->first()?->email,
                'subscription_ends_at' => $b->subscription_ends_at,
            ]);

        // Past due / expired active
        $pastDue = Business::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('subscription_status', 'past_due')
                  ->orWhere(function ($q2) {
                      $q2->where('subscription_status', 'active')
                         ->where('subscription_ends_at', '<', now());
                  });
            })
            ->with(['users' => fn($q) => $q->where('role', 'Admin')->limit(1)])
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'owner_email' => $b->users->first()?->email,
            ]);

        // New trialing businesses this month (haven't paid yet)
        $newTrialing = Business::withoutGlobalScopes()
            ->where('subscription_status', 'trialing')
            ->whereMonth('created_at', now()->month)
            ->with(['users' => fn($q) => $q->where('role', 'Admin')->limit(1)])
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'owner_email' => $b->users->first()?->email,
                'created_at'  => $b->created_at,
            ]);

        return response()->json([
            'total_businesses' => $total,
            'active'           => $active,
            'trialing'         => $trialing,
            'expired'          => $expired,
            'new_this_month'   => $newThisMonth,
            'mrr'              => $mrr,
            'expiring_soon'    => $expiringSoon,
            'past_due'         => $pastDue,
            'new_trialing'     => $newTrialing,
        ]);
    }

    /** GET /api/super-admin/businesses */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $businesses = Business::withoutGlobalScopes()
            ->withCount('users')
            ->with(['users' => fn($q) => $q->where('role', 'Admin')->limit(1)])
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'                   => $b->id,
                'name'                 => $b->name,
                'owner_email'          => $b->users->first()?->email,
                'owner_name'           => $b->users->first()?->name,
                'subscription_status'  => $b->subscription_status,
                'subscription_plan'    => $b->subscription_plan,
                'subscription_ends_at' => $b->subscription_ends_at,
                'trial_ends_at'        => $b->trial_ends_at,
                'users_count'          => $b->users_count,
                'total_revenue'        => $b->total_revenue,
                'last_payment_date'    => $b->last_payment_date,
                'last_payment_status'  => $b->last_payment_status,
                'created_at'           => $b->created_at,
            ]);

        return response()->json($businesses);
    }

    /** GET /api/super-admin/payments */
    public function payments(): \Illuminate\Http\JsonResponse
    {
        $response = Http::withToken($this->secretKey)
            ->get('https://api.paystack.co/transaction', [
                'perPage' => 50,
                'status'  => 'success',
            ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Could not fetch transactions from Paystack.'], 502);
        }

        $transactions = collect($response->json('data'))->map(fn($t) => [
            'reference' => $t['reference'],
            'amount'    => $t['amount'] / 100,
            'currency'  => $t['currency'],
            'status'    => $t['status'],
            'email'     => $t['customer']['email'] ?? null,
            'plan_type' => $t['metadata']['plan_type'] ?? null,
            'paid_at'   => $t['paid_at'],
        ]);

        return response()->json([
            'total_revenue' => $transactions->sum('amount'),
            'total_count'   => $transactions->count(),
            'transactions'  => $transactions,
        ]);
    }

    /** PUT /api/super-admin/businesses/{id}/subscription */
    public function updateSubscription(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'subscription_status'  => 'required|string|in:active,trialing,past_due,cancelled',
            'subscription_ends_at' => 'nullable|date',
            'subscription_plan'    => 'nullable|string|in:monthly,annual',
        ]);

        $business = Business::withoutGlobalScopes()->findOrFail($id);

        $business->update([
            'subscription_status'  => $request->input('subscription_status'),
            'subscription_ends_at' => $request->input('subscription_ends_at')
                ? Carbon::parse($request->input('subscription_ends_at'))
                : null,
            'subscription_plan'    => $request->input('subscription_plan', $business->subscription_plan),
        ]);

        return response()->json([
            'message'  => 'Business subscription updated successfully.',
            'business' => $business,
        ]);
    }

    /** GET /api/super-admin/businesses/{id}/details */
    public function businessDetails($id): \Illuminate\Http\JsonResponse
    {
        $business = Business::withoutGlobalScopes()
            ->withCount('users')
            ->with(['users' => fn($q) => $q->where('role', 'Admin')->limit(1)])
            ->findOrFail($id);

        $admin = $business->users->first();
        $email = $admin?->email ?? $business->email;

        // Fetch recent payments for this business from Paystack if we have email
        $transactions = [];
        if ($email) {
            $response = Http::withToken($this->secretKey)
                ->get('https://api.paystack.co/transaction', [
                    'perPage' => 10,
                    'customer' => $business->paystack_customer_code ?? null, // we can search by customer code if known
                    // Paystack does not filter directly by email well, but we can just fetch recent globally and filter, 
                    // or better, if they have a customer code, use it. If not, just rely on our local tracking.
                ]);
            if ($response->successful()) {
                $txData = collect($response->json('data'));
                // Filter transactions locally if we couldn't use customer_code
                if (!$business->paystack_customer_code) {
                    $txData = $txData->filter(fn($t) => ($t['customer']['email'] ?? '') === $email || ($t['metadata']['email'] ?? '') === $email);
                }
                $transactions = $txData->map(fn($t) => [
                    'reference' => $t['reference'],
                    'amount'    => $t['amount'] / 100,
                    'currency'  => $t['currency'],
                    'status'    => $t['status'],
                    'plan_type' => $t['metadata']['plan_type'] ?? null,
                    'paid_at'   => $t['paid_at'],
                ])->values()->take(5);
            }
        }

        // Mock recent activity (we don't have global AuditLog access easily, just grab recent from this business)
        $recentActivity = \App\Models\AuditLog::where('business_id', $business->id)
            ->latest()->take(5)->get()->map(fn($log) => [
                'action' => $log->action,
                'model' => class_basename($log->auditable_type),
                'created_at' => $log->created_at
            ]);

        return response()->json([
            'business' => [
                'id'                   => $business->id,
                'name'                 => $business->name,
                'owner_name'           => $admin?->name,
                'owner_email'          => $email,
                'subscription_status'  => $business->subscription_status,
                'subscription_plan'    => $business->subscription_plan,
                'subscription_ends_at' => $business->subscription_ends_at,
                'trial_ends_at'        => $business->trial_ends_at,
                'users_count'          => $business->users_count,
                'total_revenue'        => $business->total_revenue,
                'last_payment_date'    => $business->last_payment_date,
                'last_payment_status'  => $business->last_payment_status,
                'created_at'           => $business->created_at,
            ],
            'recent_payments' => $transactions,
            'recent_activity' => $recentActivity,
        ]);
    }

    /** POST /api/super-admin/businesses/{id}/suspend */
    public function suspend($id): \Illuminate\Http\JsonResponse
    {
        $business = Business::withoutGlobalScopes()->findOrFail($id);
        $business->update(['subscription_status' => 'past_due']);
        return response()->json(['message' => 'Business suspended']);
    }

    /** POST /api/super-admin/businesses/{id}/activate */
    public function activate($id): \Illuminate\Http\JsonResponse
    {
        $business = Business::withoutGlobalScopes()->findOrFail($id);
        $business->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(), // Give 1 month manually
        ]);
        return response()->json(['message' => 'Business activated manually']);
    }

    /** POST /api/super-admin/businesses/{id}/extend-trial */
    public function extendTrial($id): \Illuminate\Http\JsonResponse
    {
        $business = Business::withoutGlobalScopes()->findOrFail($id);
        $business->update([
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
        return response()->json(['message' => 'Trial extended by 14 days']);
    }

    /** POST /api/super-admin/businesses/{id}/change-plan */
    public function changePlan(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate(['plan' => 'required|in:monthly,annual']);
        $business = Business::withoutGlobalScopes()->findOrFail($id);
        $business->update(['subscription_plan' => $request->plan]);
        return response()->json(['message' => 'Plan changed successfully']);
    }
}
