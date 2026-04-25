<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{
    private string $secretKey;
    private string $annualPlan;
    private string $monthlyPlan;

    public function __construct()
    {
        $this->secretKey  = config('services.paystack.secret_key');
        $this->annualPlan = config('services.paystack.annual_plan');
        $this->monthlyPlan = config('services.paystack.monthly_plan');
    }

    /** GET /api/subscription — returns current subscription status */
    public function status(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        return response()->json([
            'subscription_status'  => $business->subscription_status,
            'subscription_plan'    => $business->subscription_plan,
            'subscription_ends_at' => $business->subscription_ends_at,
            'trial_ends_at'        => $business->trial_ends_at,
            'trial_days_remaining' => $business->trialDaysRemaining(),
            'is_access_allowed'    => $business->isAccessAllowed(),
        ]);
    }

    /** POST /api/subscription/initiate — create Paystack payment URL */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate(['plan' => 'required|in:monthly,annual']);
        
        $user     = $request->user();
        $business = $user->business;

        $planType = $request->input('plan');

        $response = Http::withToken($this->secretKey)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email'    => $user->email,
                'amount'   => $planType === 'annual' ? 250000 : 20000, // strictly 250,000 pesewas (2500 GHS) or 20,000 pesewas (200 GHS)
                'channels' => ['card', 'mobile_money'],
                'metadata' => [
                    'business_id' => $business->id,
                    'user_id'     => $user->id,
                    'plan_type'   => $planType,
                    'cancel_action' => url('/billing'),
                ],
                'callback_url' => config('app.url') . '/api/subscription/callback',
            ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Could not initiate payment. Try again.',
                'paystack_error' => $response->json('message')
            ], 502);
        }

        return response()->json([
            'authorization_url' => $response->json('data.authorization_url'),
            'reference'         => $response->json('data.reference'),
        ]);
    }

    /** GET /api/subscription/callback — called by Paystack after payment */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $reference = $request->query('reference');
        if (!$reference) {
            return redirect(env('FRONTEND_URL', 'http://localhost:4200') . '/billing?status=failed');
        }

        $response = Http::withToken($this->secretKey)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->successful() || $response->json('data.status') !== 'success') {
            return redirect(env('FRONTEND_URL', 'http://localhost:4200') . '/billing?status=failed');
        }

        $data       = $response->json('data');
        $businessId = $data['metadata']['business_id'] ?? null;

        if ($businessId) {
            $business = Business::withoutGlobalScopes()->find($businessId);
            if ($business) {
                $planType = $data['metadata']['plan_type'] ?? 'monthly';
                $isAnnual = $planType === 'annual';

                $business->update([
                    'subscription_status'         => 'active',
                    'subscription_plan'           => $isAnnual ? 'annual' : 'monthly',
                    'paystack_customer_code'      => $data['customer']['customer_code'] ?? null,
                    'paystack_email_token'        => $data['customer']['email_token'] ?? null,
                    'subscription_ends_at'        => $isAnnual ? now()->addYear() : now()->addMonth(),
                ]);
            }
        }

        return redirect(env('FRONTEND_URL', 'http://localhost:4200') . '/billing?status=success');
    }

    /** POST /api/webhooks/paystack — server-to-server events */
    public function webhook(Request $request): JsonResponse
    {
        // Verify the webhook signature
        $hash = hash_hmac('sha512', $request->getContent(), $this->secretKey);
        if ($hash !== $request->header('x-paystack-signature')) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->json('event');
        $data  = $request->json('data');

        if ($event === 'subscription.create') {
            $business = Business::withoutGlobalScopes()
                ->where('paystack_customer_code', $data['customer']['customer_code'])
                ->first();

            if ($business) {
                $planData = $data['plan'] ?? null;
                $planCode = is_array($planData) ? ($planData['plan_code'] ?? null) : $planData;
                $isAnnual = $planCode === $this->annualPlan;
                $business->update([
                    'subscription_status'        => 'active',
                    'subscription_plan'          => $isAnnual ? 'annual' : 'monthly',
                    'paystack_subscription_code' => $data['subscription_code'] ?? null,
                    'subscription_ends_at'       => now()->addYear(),
                ]);
            }
        }

        if ($event === 'invoice.payment_failed') {
            $business = Business::withoutGlobalScopes()
                ->where('paystack_customer_code', $data['customer']['customer_code'])
                ->first();

            if ($business) {
                $business->update(['subscription_status' => 'past_due']);
            }
        }

        if ($event === 'subscription.disable') {
            $business = Business::withoutGlobalScopes()
                ->where('paystack_subscription_code', $data['subscription_code'])
                ->first();

            if ($business) {
                $business->update(['subscription_status' => 'cancelled']);
            }
        }

        // Handle successful one-time payment
        if ($event === 'charge.success') {
            $businessId = $data['metadata']['business_id'] ?? null;
            if ($businessId) {
                $business = Business::withoutGlobalScopes()->find($businessId);

                if ($business) {
                    $planType = $data['metadata']['plan_type'] ?? 'monthly';
                    $isAnnual = $planType === 'annual';
                    $business->update([
                        'subscription_status' => 'active',
                        'subscription_plan'   => $isAnnual ? 'annual' : 'monthly',
                        'subscription_ends_at' => $isAnnual ? now()->addYear() : now()->addMonth(),
                    ]);
                }
            }
        }

        return response()->json(['message' => 'ok']);
    }
}
