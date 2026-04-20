<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\LogsActivity;

class Debt extends Model
{
    use LogsActivity;

    protected $fillable = [
        'wholesale_sale_id', 'client_id',
        'amount_paid', 'old_debt', 'new_debt', 'note',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'wholesale_sale_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
