<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class StockTransfer extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'from_type',
        'from_product_id',
        'from_product_name',
        'source_unit_id',
        'source_unit_name',
        'source_unit_quantity_in_base',
        'source_base_unit',
        'to_type',
        'to_product_id',
        'to_product_name',
        'to_business_id',
        'auto_created',
        'display_quantity',
        'quantity',
        'note',
        'transferred_by',
    ];

    protected $casts = [
        'auto_created' => 'boolean',
        'display_quantity' => 'integer',
        'source_unit_quantity_in_base' => 'integer',
        'quantity'     => 'integer',
    ];

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function toBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'to_business_id');
    }
}
