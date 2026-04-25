<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class Expense extends Model
{
    use BelongsToBusiness;
    protected $fillable = [
        'title',
        'amount',
        'category',
        'note',
        'expense_date',
        'recorded_by',
        'business_id',
    ];

    protected $casts = [
        'amount'       => 'float',
        'expense_date' => 'date:Y-m-d',
    ];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
