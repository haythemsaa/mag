<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GeofenceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'geofence_id',
        'vehicle_id',
        'driver_id',
        'gps_position_id',
        'event_type',
        'event_time',
        'latitude',
        'longitude',
        'speed_kmh',
        'alert_sent',
        'alert_sent_at',
        'notes',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed_kmh' => 'integer',
        'alert_sent' => 'boolean',
        'alert_sent_at' => 'datetime',
    ];

    protected $hidden = [];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function gpsPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class);
    }

    /**
     * Scopes
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeEntry(Builder $query): Builder
    {
        return $query->where('event_type', 'entry');
    }

    public function scopeExit(Builder $query): Builder
    {
        return $query->where('event_type', 'exit');
    }

    public function scopeByGeofence(Builder $query, int $geofenceId): Builder
    {
        return $query->where('geofence_id', $geofenceId);
    }

    public function scopeByVehicle(Builder $query, int $vehicleId): Builder
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeByDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeAlertPending(Builder $query): Builder
    {
        return $query->where('alert_sent', false);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('event_time', '>=', now()->subHours($hours));
    }

    /**
     * Business Logic Methods
     */

    /**
     * Mark alert as sent
     */
    public function markAlertAsSent(): void
    {
        $this->update([
            'alert_sent' => true,
            'alert_sent_at' => now(),
        ]);
    }
}
