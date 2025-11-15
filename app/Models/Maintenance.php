<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Maintenance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'workshop_id',
        'driver_id',
        'type',
        'category',
        'reference_number',
        'scheduled_date',
        'completed_date',
        'start_time',
        'end_time',
        'mileage_at_service',
        'next_service_mileage',
        'next_service_date',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'description',
        'work_done',
        'parts_replaced',
        'recommendations',
        'invoice_number',
        'invoice_document',
        'status',
        'downtime_hours',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'next_service_date' => 'date',
        'mileage_at_service' => 'integer',
        'next_service_mileage' => 'integer',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'downtime_hours' => 'integer',
    ];

    /**
     * Get the organization that owns the maintenance
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the vehicle that owns the maintenance
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the workshop
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Get the driver
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Check if maintenance is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->scheduled_date || $this->status !== 'scheduled') {
            return false;
        }

        return $this->scheduled_date < now();
    }

    /**
     * Calculate actual cost
     */
    public function calculateTotalCost(): float
    {
        return $this->labor_cost + $this->parts_cost;
    }

    /**
     * Scope for upcoming maintenance
     */
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [now(), now()->addDays($days)]);
    }
}
