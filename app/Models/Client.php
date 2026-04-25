<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\LogsActivity;
use App\Traits\BelongsToBusiness;

class Client extends Model
{
    use LogsActivity, BelongsToBusiness;

    protected $fillable = [
        'name', 'contact', 'location', 'email', 'total_debt', 'business_id',
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
