<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'siret',
        'vat_number',
        'address',
        'postal_code',
        'city',
        'country',
        'phone',
        'email',
        'logo',
        'parent_id',
        'subscription_plan',
        'max_vehicles',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_vehicles' => 'integer',
    ];

    /**
     * Get the parent organization
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * Get the child organizations
     */
    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /**
     * Get the sites for the organization
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get the vehicles for the organization
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Get the drivers for the organization
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * Get the contracts for the organization
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the maintenances for the organization
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Get the fuel transactions for the organization
     */
    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    /**
     * Get the costs for the organization
     */
    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    /**
     * Get the workshops for the organization
     */
    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }

    /**
     * Check if organization is at vehicle limit
     */
    public function isAtVehicleLimit(): bool
    {
        return $this->vehicles()->count() >= $this->max_vehicles;
    }

    /**
     * Get remaining vehicle slots
     */
    public function remainingVehicleSlots(): int
    {
        return max(0, $this->max_vehicles - $this->vehicles()->count());
    }
}
