<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Traits\LogsActivity;
use App\Traits\BelongsToBusiness;

class RetailSale extends Model
{
    use LogsActivity, BelongsToBusiness;

    protected $fillable = [
        'user_id', 'receipt_number', 'total_amount',
        'total_cost', 'profit', 'payment_method', 'momo_number', 'status', 'business_id',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(Reversal::class);
    }
}
