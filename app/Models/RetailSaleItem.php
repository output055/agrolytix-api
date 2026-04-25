<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class RetailSaleItem extends Model
{
    use BelongsToBusiness;
    protected $fillable = [
        'retail_sale_id', 'product_id', 'product_name',
        'unit_name', 'quantity', 'quantity_base',
        'unit_price', 'cost_price', 'subtotal', 'business_id',
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
