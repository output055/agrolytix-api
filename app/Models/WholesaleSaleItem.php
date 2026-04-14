<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleSaleItem extends Model
{
    protected $fillable = [
        'wholesale_sale_id', 'wholesale_product_id', 'product_name',
        'unit_name', 'quantity', 'quantity_base',
        'unit_price', 'cost_price', 'subtotal',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'wholesale_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'wholesale_product_id');
    }
}
