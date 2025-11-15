<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPosition extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'latitude',
        'longitude',
        'altitude',
        'accuracy',
        'speed',
        'heading',
        'direction',
        'engine_on',
        'engine_status',
        'fuel_level',
        'battery_level',
        'odometer',
        'address',
        'city',
        'country',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'altitude' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'integer',
        'engine_on' => 'boolean',
        'fuel_level' => 'integer',
        'battery_level' => 'integer',
        'odometer' => 'integer',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the vehicle
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the driver
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Check if vehicle is moving
     */
    public function isMoving(): bool
    {
        return $this->speed > 5; // km/h
    }

    /**
     * Check if vehicle is speeding
     */
    public function isSpeeding(int $speedLimit = 130): bool
    {
        return $this->speed > $speedLimit;
    }

    /**
     * Scope for recent positions
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for vehicle positions
     */
    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId)
            ->orderBy('recorded_at', 'desc');
    }
}
