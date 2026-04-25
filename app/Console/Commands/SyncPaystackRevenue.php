<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Illuminate\Support\Facades\Http;

class SyncPaystackRevenue extends Command
{
    protected $signature = 'paystack:sync-revenue';
    protected $description = 'Sync historical paystack revenue to businesses';

    public function handle()
    {
        $secretKey = config('services.paystack.secret_key');
        if (!$secretKey) {
            $this->error('No secret key');
            return;
        }
        
        $this->info('Fetching transactions...');
        $response = Http::withToken($secretKey)
            ->get("https://api.paystack.co/transaction", ['perPage' => 100]);

        if (!$response->successful()) {
            $this->error('Failed to fetch from Paystack.');
            return;
        }

        $transactions = $response->json('data');
        $this->info('Found ' . count($transactions) . ' recent transactions.');

        $revenueMap = [];
        $latestDateMap = [];

        foreach ($transactions as $t) {
            if ($t['status'] === 'success') {
                $email = $t['customer']['email'] ?? null;
                $metadataEmail = $t['metadata']['email'] ?? null;
                
                $targetEmail = $metadataEmail ?: $email;

                if ($targetEmail) {
                    $amount = $t['amount'] / 100;
                    if (!isset($revenueMap[$targetEmail])) {
                        $revenueMap[$targetEmail] = 0;
                    }
                    $revenueMap[$targetEmail] += $amount;

                    $date = \Carbon\Carbon::parse($t['paid_at']);
                    if (!isset($latestDateMap[$targetEmail]) || $date->gt($latestDateMap[$targetEmail])) {
                        $latestDateMap[$targetEmail] = $date;
                    }
                }
            }
        }

        foreach ($revenueMap as $email => $total) {
            // Find by owner email since we don't always have business ID
            $business = Business::where('email', $email)->orWhereHas('users', function($q) use ($email) {
                $q->where('email', $email)->where('role', 'Admin');
            })->first();

            if ($business) {
                $business->update([
                    'total_revenue' => $total,
                    'last_payment_date' => $latestDateMap[$email],
                    'last_payment_status' => 'success'
                ]);
                $this->info("Updated {$business->name}: GHS {$total}");
            } else {
                $this->warn("No business found for {$email}");
            }
        }

        $this->info('Done!');
    }
}
