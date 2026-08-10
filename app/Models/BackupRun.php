<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $fillable = [
        'target',
        'status',
        'initiated_by_user_id',
        'started_at',
        'finished_at',
        'records_synced',
        'tables_synced',
        'error_message',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'tables_synced' => 'array',
    ];

    /** The admin user who triggered this backup run. */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** Mark this run as completed. */
    public function markCompleted(int $totalRecords, array $tableResults): void
    {
        $this->update([
            'status'         => 'completed',
            'finished_at'    => now(),
            'records_synced' => $totalRecords,
            'tables_synced'  => $tableResults,
        ]);
    }

    /** Mark this run as failed. */
    public function markFailed(string $message, array $tableResults = []): void
    {
        $this->update([
            'status'         => 'failed',
            'finished_at'    => now(),
            'error_message'  => $message,
            'tables_synced'  => $tableResults ?: null,
        ]);
    }
}
