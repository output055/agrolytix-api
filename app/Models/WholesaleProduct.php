<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WholesaleProduct extends Model
{
    protected $fillable = [
        'name', 'category', 'description',
        'cost_price', 'sell_price', 'quantity',
        'base_unit', 'low_stock_alert',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(WholesaleProductUnit::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_alert;
    }
}
