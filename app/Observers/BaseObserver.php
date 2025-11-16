<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BaseObserver
{
    /**
     * Log activity for a model event
     */
    protected function logActivity(Model $model, string $event): void
    {
        // Skip logging if user is not authenticated (e.g., seeders, queue jobs)
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        $request = request();

        // Get old and new values
        $oldValues = $event === 'updated' ? $model->getOriginal() : null;
        $newValues = $model->getAttributes();

        // Calculate changes for update events
        $changes = null;
        if ($event === 'updated' && $oldValues) {
            $changes = array_diff_assoc($newValues, $oldValues);
            // Remove unchanged values
            $changes = array_filter($changes, function ($value, $key) use ($oldValues) {
                return !isset($oldValues[$key]) || $oldValues[$key] !== $value;
            }, ARRAY_FILTER_USE_BOTH);
        }

        // Create activity log
        ActivityLog::create([
            'organization_id' => $model->organization_id ?? $user->organization_id,
            'user_id' => $user->id,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $changes,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Remove sensitive fields from logged data
     */
    protected function removeSensitiveFields(array $data): array
    {
        $sensitiveFields = ['password', 'remember_token', 'api_token'];

        foreach ($sensitiveFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
