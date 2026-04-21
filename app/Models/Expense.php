<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'title',
        'amount',
        'category',
        'note',
        'expense_date',
        'recorded_by',
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
