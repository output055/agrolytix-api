<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'contact', 'location', 'email', 'total_debt',
    ];

    public function wholesaleSales(): HasMany
    {
        return $this->hasMany(WholesaleSale::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(Debt::class);
    }
}
