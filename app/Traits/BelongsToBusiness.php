<?php

namespace App\Traits;

use App\Models\Business;
use App\Models\Scopes\BusinessScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    /**
     * The "booted" method of the model.
     */
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->business_id && empty($model->business_id)) {
                $model->business_id = auth()->user()->business_id;
            }
        });
    }

    /**
     * Get the business that owns the model.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
