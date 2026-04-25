<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'trial_ends_at',
        'subscription_status', 'subscription_plan',
        'paystack_customer_code', 'paystack_subscription_code',
        'paystack_email_token', 'subscription_ends_at',
    ];

    protected $casts = [
        'trial_ends_at'       => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /** Returns true if the business is allowed to use the platform */
    public function isAccessAllowed(): bool
    {
        // Active subscriber: always allowed
        if ($this->subscription_status === 'active' && $this->subscription_ends_at?->isFuture()) {
            return true;
        }

        // Still in trial
        if ($this->subscription_status === 'trialing' && $this->trial_ends_at?->isFuture()) {
            return true;
        }

        return false;
    }

    /** Days left in trial */
    public function trialDaysRemaining(): int
    {
        if (!$this->trial_ends_at) return 0;
        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public function users() { return $this->hasMany(User::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function wholesaleProducts() { return $this->hasMany(WholesaleProduct::class); }
    public function retailSales() { return $this->hasMany(RetailSale::class); }
    public function wholesaleSales() { return $this->hasMany(WholesaleSale::class); }
    public function clients() { return $this->hasMany(Client::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }
}
