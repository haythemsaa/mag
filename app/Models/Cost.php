<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'category',
        'subcategory',
        'date',
        'amount',
        'currency',
        'supplier_name',
        'invoice_number',
        'invoice_document',
        'reference',
        'description',
        'mileage',
        'validated',
        'validated_by',
        'validated_at',
        'account_code',
        'vat_deductible',
        'vat_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'mileage' => 'integer',
        'validated' => 'boolean',
        'validated_at' => 'datetime',
        'vat_deductible' => 'boolean',
        'vat_amount' => 'decimal:2',
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
     * Get the user who validated
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Calculate cost per km
     */
    public function costPerKm(): ?float
    {
        if (!$this->mileage || $this->mileage == 0) {
            return null;
        }

        return $this->amount / $this->mileage;
    }

    /**
     * Scope for category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
