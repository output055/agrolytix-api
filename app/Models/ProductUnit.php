<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class ProductUnit extends Model
{
    use BelongsToBusiness;
    protected $fillable = [
        'product_id', 'unit_name', 'quantity_in_base',
        'price', 'is_bulk', 'bulk_discount_pct', 'business_id',
    ];

    protected $casts = ['is_bulk' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Effective price after bulk discount applied */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->is_bulk && $this->bulk_discount_pct > 0) {
            return round($this->price * (1 - $this->bulk_discount_pct / 100), 2);
        }
        return (float) $this->price;
    }
}
