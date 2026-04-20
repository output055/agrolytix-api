<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\LogsActivity;

class Product extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'category', 'description',
        'cost_price', 'sell_price', 'quantity',
        'base_unit', 'low_stock_alert',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function retailSaleItems(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_alert;
    }
}
