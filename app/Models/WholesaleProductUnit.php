<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleProductUnit extends Model
{
    protected $fillable = [
        'wholesale_product_id', 'unit_name', 'quantity_in_base',
        'price', 'is_bulk', 'bulk_discount_pct',
    ];

    protected $casts = ['is_bulk' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class);
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->is_bulk && $this->bulk_discount_pct > 0) {
            return round($this->price * (1 - $this->bulk_discount_pct / 100), 2);
        }
        return (float) $this->price;
    }
}
