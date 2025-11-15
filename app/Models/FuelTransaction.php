<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelTransaction extends Model
{
    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'transaction_date',
        'station_name',
        'station_address',
        'latitude',
        'longitude',
        'fuel_type',
        'quantity',
        'unit_price',
        'total_cost',
        'currency',
        'mileage',
        'odometer_reading',
        'payment_method',
        'card_number',
        'invoice_number',
        'validated',
        'anomaly_detected',
        'anomaly_reason',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:3',
        'total_cost' => 'decimal:2',
        'mileage' => 'integer',
        'odometer_reading' => 'integer',
        'validated' => 'boolean',
        'anomaly_detected' => 'boolean',
    ];

    /**
     * Get the organization
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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
     * Calculate consumption (L/100km)
     */
    public function calculateConsumption(int $previousMileage): ?float
    {
        if (!$this->mileage || !$previousMileage) {
            return null;
        }

        $distance = $this->mileage - $previousMileage;

        if ($distance <= 0) {
            return null;
        }

        return ($this->quantity / $distance) * 100;
    }

    /**
     * Detect price anomaly
     */
    public function detectPriceAnomaly(float $averagePrice, float $threshold = 0.2): bool
    {
        $deviation = abs($this->unit_price - $averagePrice) / $averagePrice;
        return $deviation > $threshold;
    }
}
