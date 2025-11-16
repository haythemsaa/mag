<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Geofence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'shape',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'polygon_coordinates',
        'alert_on_entry',
        'alert_on_exit',
        'is_active',
        'active_from_time',
        'active_to_time',
        'active_days',
        'address',
        'city',
        'postal_code',
        'country',
        'color',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:7',
        'center_longitude' => 'decimal:7',
        'polygon_coordinates' => 'array',
        'active_days' => 'array',
        'alert_on_entry' => 'boolean',
        'alert_on_exit' => 'boolean',
        'is_active' => 'boolean',
        'radius_meters' => 'integer',
    ];

    protected $hidden = [];

    protected $appends = [
        'has_time_restrictions',
        'total_events_count',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    /**
     * Scopes
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWithAlerts(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('alert_on_entry', true)
              ->orWhere('alert_on_exit', true);
        });
    }

    public function scopeCircle(Builder $query): Builder
    {
        return $query->where('shape', 'circle');
    }

    public function scopePolygon(Builder $query): Builder
    {
        return $query->where('shape', 'polygon');
    }

    /**
     * Accessors
     */
    public function getHasTimeRestrictionsAttribute(): bool
    {
        return !is_null($this->active_from_time)
            || !is_null($this->active_to_time)
            || !is_null($this->active_days);
    }

    public function getTotalEventsCountAttribute(): int
    {
        return $this->events()->count();
    }

    /**
     * Business Logic Methods
     */

    /**
     * Check if a point is inside this geofence
     */
    public function containsPoint(float $latitude, float $longitude): bool
    {
        if ($this->shape === 'circle') {
            return $this->pointInCircle($latitude, $longitude);
        } else {
            return $this->pointInPolygon($latitude, $longitude);
        }
    }

    /**
     * Check if point is inside circle geofence
     */
    private function pointInCircle(float $latitude, float $longitude): bool
    {
        if (!$this->center_latitude || !$this->center_longitude || !$this->radius_meters) {
            return false;
        }

        $distance = $this->calculateDistance(
            $this->center_latitude,
            $this->center_longitude,
            $latitude,
            $longitude
        );

        return $distance <= $this->radius_meters;
    }

    /**
     * Check if point is inside polygon geofence using Ray Casting algorithm
     */
    private function pointInPolygon(float $latitude, float $longitude): bool
    {
        if (!$this->polygon_coordinates || count($this->polygon_coordinates) < 3) {
            return false;
        }

        $vertices = $this->polygon_coordinates;
        $numVertices = count($vertices);
        $inside = false;

        for ($i = 0, $j = $numVertices - 1; $i < $numVertices; $j = $i++) {
            $xi = $vertices[$i]['lng'];
            $yi = $vertices[$i]['lat'];
            $xj = $vertices[$j]['lng'];
            $yj = $vertices[$j]['lat'];

            $intersect = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Calculate distance between two points using Haversine formula (in meters)
     */
    private function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // Earth's radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if geofence is active at a given time
     */
    public function isActiveAt(\DateTime $datetime): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check day restriction
        if ($this->active_days && !empty($this->active_days)) {
            $dayOfWeek = (int) $datetime->format('N'); // 1 (Monday) to 7 (Sunday)
            if (!in_array($dayOfWeek, $this->active_days)) {
                return false;
            }
        }

        // Check time restriction
        if ($this->active_from_time && $this->active_to_time) {
            $currentTime = $datetime->format('H:i:s');
            if ($currentTime < $this->active_from_time || $currentTime > $this->active_to_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get statistics for this geofence
     */
    public function getStatistics(): array
    {
        return [
            'total_events' => $this->events()->count(),
            'entry_events' => $this->events()->where('event_type', 'entry')->count(),
            'exit_events' => $this->events()->where('event_type', 'exit')->count(),
            'unique_vehicles' => $this->events()->distinct('vehicle_id')->count('vehicle_id'),
            'last_event' => $this->events()->latest('event_time')->first()?->event_time,
        ];
    }
}
