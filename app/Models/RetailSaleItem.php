<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailSaleItem extends Model
{
    protected $fillable = [
        'retail_sale_id', 'product_id', 'product_name',
        'unit_name', 'quantity', 'quantity_base',
        'unit_price', 'cost_price', 'subtotal',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
