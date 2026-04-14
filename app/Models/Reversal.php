<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reversal extends Model
{
    protected $fillable = [
        'retail_sale_id', 'user_id', 'reason', 'amount_reversed',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
