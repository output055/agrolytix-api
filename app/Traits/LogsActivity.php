<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Boot the trait.
     */
    protected static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            static::logActivity($model, 'CREATED');
        });

        static::updated(function (Model $model) {
            // Only log if there are actual changes
            if ($model->wasChanged()) {
                static::logActivity($model, 'UPDATED');
            }
        });

        static::deleted(function (Model $model) {
            static::logActivity($model, 'DELETED');
        });
    }

    /**
     * Log the activity to the database.
     */
    protected static function logActivity(Model $model, string $action): void
    {
        $metadata = [
            'old' => [],
            'new' => [],
        ];

        if ($action === 'UPDATED') {
            $changes = $model->getChanges();
            foreach ($changes as $key => $value) {
                // Skip timestamp fields
                if (in_array($key, ['updated_at', 'last_login_at'])) {
                    continue;
                }
                // Mask sensitive fields
                if (in_array($key, ['password'])) {
                    $metadata['old'][$key] = '********';
                    $metadata['new'][$key] = '********';
                    continue;
                }
                $metadata['old'][$key] = $model->getOriginal($key);
                $metadata['new'][$key] = $value;
            }
        } elseif ($action === 'CREATED') {
            $attributes = $model->getAttributes();
            foreach ($attributes as $key => $value) {
                if (in_array($key, ['updated_at', 'created_at', 'password'])) {
                    if ($key === 'password') $metadata['new'][$key] = '********';
                    continue;
                }
                $metadata['new'][$key] = $value;
            }
        } elseif ($action === 'DELETED') {
            $metadata['old'] = $model->getOriginal();
            unset($metadata['old']['password']); // Safety first
        }

        AuditLog::log(
            action: "{$action}_" . strtoupper(class_basename($model)),
            entityType: strtolower(class_basename($model)),
            entityId: $model->id,
            metadata: $metadata
        );
    }
}
