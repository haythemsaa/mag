<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'stop_number',
        'optimized_stop_number',
        'type',
        'status',
        'location_name',
        'address',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'contact_email',
        'planned_arrival',
        'planned_departure',
        'actual_arrival',
        'actual_departure',
        'service_duration_minutes',
        'actual_service_duration_minutes',
        'time_window_start',
        'time_window_end',
        'distance_from_previous_km',
        'duration_from_previous_minutes',
        'instructions',
        'notes',
        'requires_signature',
        'requires_photo',
        'signature_path',
        'photo_paths',
        'completion_notes',
        'failure_reason',
        'reference_number',
        'package_count',
        'package_weight_kg',
        'package_volume_m3',
        'priority',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'planned_arrival' => 'datetime',
        'planned_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'actual_departure' => 'datetime',
        'time_window_start' => 'datetime:H:i',
        'time_window_end' => 'datetime:H:i',
        'distance_from_previous_km' => 'decimal:2',
        'package_weight_kg' => 'decimal:2',
        'package_volume_m3' => 'decimal:3',
        'requires_signature' => 'boolean',
        'requires_photo' => 'boolean',
        'photo_paths' => 'array',
    ];

    /**
     * Relationships
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithinTimeWindow($query, $time = null)
    {
        $time = $time ?? now()->format('H:i');

        return $query->whereNotNull('time_window_start')
            ->whereNotNull('time_window_end')
            ->whereTime('time_window_start', '<=', $time)
            ->whereTime('time_window_end', '>=', $time);
    }

    /**
     * Workflow methods
     */
    public function markAsArrived(): void
    {
        $this->update([
            'status' => 'arrived',
            'actual_arrival' => now(),
        ]);
    }

    public function startService(): void
    {
        $this->update([
            'status' => 'in_progress',
        ]);
    }

    public function completeStop(array $data = []): void
    {
        $actualServiceDuration = null;
        if ($this->actual_arrival) {
            $actualServiceDuration = now()->diffInMinutes($this->actual_arrival);
        }

        $this->update(array_merge([
            'status' => 'completed',
            'actual_departure' => now(),
            'actual_service_duration_minutes' => $actualServiceDuration,
        ], $data));

        // Update route completed stops count
        $this->route->increment('completed_stops_count');
    }

    public function skipStop(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'failure_reason' => $reason,
        ]);
    }

    public function failStop(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function uploadSignature(string $path): void
    {
        $this->update([
            'signature_path' => $path,
        ]);
    }

    public function uploadPhoto(string $path): void
    {
        $photos = $this->photo_paths ?? [];
        $photos[] = $path;

        $this->update([
            'photo_paths' => $photos,
        ]);
    }

    /**
     * Helper methods
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isArrived(): bool
    {
        return $this->status === 'arrived';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isWithinTimeWindow(): bool
    {
        if (!$this->time_window_start || !$this->time_window_end) {
            return true; // No time window constraint
        }

        $now = now()->format('H:i');
        return $now >= $this->time_window_start && $now <= $this->time_window_end;
    }

    public function isLate(): bool
    {
        if (!$this->planned_arrival || !$this->actual_arrival) {
            return false;
        }

        return $this->actual_arrival->gt($this->planned_arrival);
    }

    public function getDelayMinutes(): ?int
    {
        if (!$this->planned_arrival || !$this->actual_arrival) {
            return null;
        }

        return $this->actual_arrival->diffInMinutes($this->planned_arrival, false);
    }

    public function getActualServiceDuration(): ?int
    {
        if (!$this->actual_arrival || !$this->actual_departure) {
            return null;
        }

        return $this->actual_departure->diffInMinutes($this->actual_arrival);
    }

    /**
     * Distance calculation from another stop using Haversine formula
     */
    public function distanceFrom(RouteStop $otherStop): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($otherStop->latitude);
        $lon2 = deg2rad($otherStop->longitude);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1) * cos($lat2) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
