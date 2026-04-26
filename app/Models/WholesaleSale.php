<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\LogsActivity;
use App\Traits\BelongsToBusiness;

class WholesaleSale extends Model
{
    use LogsActivity, BelongsToBusiness;

    protected $fillable = [
        'user_id', 'client_id', 'receipt_number',
        'total_amount', 'total_cost', 'profit', 'payment_method', 'momo_number',
        'amount_paid', 'debt', 'status', 'business_id',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleSaleItem::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(Debt::class);
    }
}
