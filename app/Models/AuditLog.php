<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class AuditLog extends Model
{
    use BelongsToBusiness;
    protected $fillable = [
        'user_id',
        'action_type',
        'entity_type',
        'entity_id',
        'status',
        'severity',
        'ip_address',
        'user_agent',
        'metadata',
        'business_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to log an event.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $status = 'success',
        string $severity = 'INFO',
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id'     => auth()->id(),
            'action_type' => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'status'      => $status,
            'severity'    => $severity,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'metadata'    => $metadata,
        ]);
    }
}
