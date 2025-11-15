<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'site_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_mobile',
        'birth_date',
        'address',
        'postal_code',
        'city',
        'license_number',
        'license_type',
        'license_issue_date',
        'license_expiry_date',
        'license_points',
        'medical_check_date',
        'next_medical_check',
        'department',
        'position',
        'hire_date',
        'contract_type',
        'eco_driving_score',
        'total_infractions',
        'total_distance',
        'is_active',
        'status',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'license_issue_date' => 'date',
        'license_expiry_date' => 'date',
        'license_points' => 'integer',
        'medical_check_date' => 'date',
        'next_medical_check' => 'date',
        'hire_date' => 'date',
        'eco_driving_score' => 'integer',
        'total_infractions' => 'integer',
        'total_distance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization that owns the driver
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the site that owns the driver
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the vehicles assigned to this driver
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'current_driver_id');
    }

    /**
     * Get the fuel transactions for the driver
     */
    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    /**
     * Get the GPS positions for the driver
     */
    public function gpsPositions(): HasMany
    {
        return $this->hasMany(GpsPosition::class);
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if license is expiring soon
     */
    public function licenseExpiringSoon(int $days = 30): bool
    {
        if (!$this->license_expiry_date) {
            return false;
        }

        return $this->license_expiry_date->diffInDays(now()) <= $days;
    }

    /**
     * Check if medical check is due
     */
    public function medicalCheckDue(): bool
    {
        if (!$this->next_medical_check) {
            return false;
        }

        return $this->next_medical_check <= now();
    }
}
