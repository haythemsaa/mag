<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'contract_number',
        'type',
        'supplier_name',
        'supplier_contact',
        'supplier_email',
        'supplier_phone',
        'start_date',
        'end_date',
        'duration_months',
        'monthly_cost',
        'total_cost',
        'mileage_limit_annual',
        'excess_mileage_cost',
        'terms',
        'document_path',
        'status',
        'auto_renewal',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_months' => 'integer',
        'monthly_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'mileage_limit_annual' => 'integer',
        'excess_mileage_cost' => 'decimal:2',
        'auto_renewal' => 'boolean',
    ];

    /**
     * Get the organization that owns the contract
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the vehicle that owns the contract
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Check if contract is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->end_date || $this->status !== 'active') {
            return false;
        }

        return $this->end_date->diffInDays(now()) <= $days;
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        if (!$this->end_date) {
            return false;
        }

        return $this->end_date < now();
    }

    /**
     * Calculate remaining months
     */
    public function remainingMonths(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        return max(0, now()->diffInMonths($this->end_date));
    }
}
