<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'trial_ends_at',
        'subscription_status', 'subscription_plan',
        'total_revenue', 'last_payment_date', 'last_payment_status',
        'paystack_customer_code', 'paystack_subscription_code',
        'paystack_email_token', 'subscription_ends_at',
        'parent_id',
    ];

    protected $casts = [
        'trial_ends_at'       => 'datetime',
        'subscription_ends_at' => 'datetime',
        'last_payment_date'    => 'datetime',
    ];

    /** Returns true if the business is allowed to use the platform */
    public function isAccessAllowed(): bool
    {
        // If this is a branch, check parent business instead
        if ($this->parent_id) {
            $parent = Business::find($this->parent_id);
            if ($parent) {
                return $parent->isAccessAllowed();
            }
        }

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

    /** Parent business (if this is a branch) */
    public function parent() { return $this->belongsTo(Business::class, 'parent_id'); }

    /** Child branches of this business */
    public function branches() { return $this->hasMany(Business::class, 'parent_id'); }

    /**
     * Returns all businesses in the same family (parent + siblings + children)
     * that are eligible for cross-branch stock transfers.
     */
    public function getSiblingBusinesses(): \Illuminate\Support\Collection
    {
        // Find the root parent ID
        $rootId = $this->parent_id ?? $this->id;

        // All businesses that share the same root: siblings, parent, and own children
        return Business::where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->where('id', '!=', $this->id)
            ->get(['id', 'name']);
    }

    /**
     * Returns an array containing the root parent ID and all child branch IDs.
     */
    public function getFamilyBusinessIds(): array
    {
        $rootId = $this->parent_id ?? $this->id;
        return Business::where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->pluck('id')
            ->toArray();
    }
}
