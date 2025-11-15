<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'site_id',
        'vin',
        'registration',
        'brand',
        'model',
        'version',
        'type',
        'category',
        'color',
        'year',
        'seats',
        'acquisition_mode',
        'acquisition_date',
        'acquisition_value',
        'current_value',
        'fuel_type',
        'fuel_capacity',
        'engine_type',
        'engine_power',
        'co2_emissions',
        'consumption_theory',
        'current_mileage',
        'initial_mileage',
        'last_mileage_update',
        'status',
        'current_driver_id',
        'registration_document',
        'technical_control_date',
        'next_technical_control',
        'insurance_policy',
        'insurance_expiry',
        'gps_device_id',
        'gps_enabled',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'seats' => 'integer',
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'fuel_capacity' => 'integer',
        'engine_power' => 'integer',
        'co2_emissions' => 'integer',
        'consumption_theory' => 'decimal:2',
        'current_mileage' => 'integer',
        'initial_mileage' => 'integer',
        'last_mileage_update' => 'date',
        'technical_control_date' => 'date',
        'next_technical_control' => 'date',
        'insurance_expiry' => 'date',
        'gps_enabled' => 'boolean',
    ];

    /**
     * Get the organization that owns the vehicle
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the site that owns the vehicle
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the current driver of the vehicle
     */
    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    /**
     * Get the contracts for the vehicle
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the maintenances for the vehicle
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Get the fuel transactions for the vehicle
     */
    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    /**
     * Get the GPS positions for the vehicle
     */
    public function gpsPositions(): HasMany
    {
        return $this->hasMany(GpsPosition::class);
    }

    /**
     * Get the costs for the vehicle
     */
    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    /**
     * Get the latest GPS position
     */
    public function latestPosition(): BelongsTo
    {
        return $this->belongsTo(GpsPosition::class, 'id', 'vehicle_id')
            ->latest('recorded_at');
    }

    /**
     * Check if vehicle is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if vehicle needs maintenance
     */
    public function needsMaintenance(): bool
    {
        if (!$this->next_service_mileage || !$this->current_mileage) {
            return false;
        }

        return $this->current_mileage >= ($this->next_service_mileage - 500);
    }

    /**
     * Check if technical control is expiring soon
     */
    public function technicalControlExpiringSoon(int $days = 30): bool
    {
        if (!$this->next_technical_control) {
            return false;
        }

        return $this->next_technical_control->diffInDays(now()) <= $days;
    }

    /**
     * Calculate total TCO (Total Cost of Ownership)
     */
    public function calculateTCO(): float
    {
        return $this->costs()->sum('amount');
    }

    /**
     * Calculate average consumption
     */
    public function calculateAverageConsumption(): ?float
    {
        $transactions = $this->fuelTransactions()
            ->where('validated', true)
            ->orderBy('transaction_date')
            ->get();

        if ($transactions->count() < 2) {
            return null;
        }

        $totalFuel = $transactions->sum('quantity');
        $totalDistance = $transactions->last()->mileage - $transactions->first()->mileage;

        if ($totalDistance <= 0) {
            return null;
        }

        return ($totalFuel / $totalDistance) * 100; // L/100km
    }
}
