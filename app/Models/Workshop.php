<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workshop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'address',
        'postal_code',
        'city',
        'country',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'contact_name',
        'services_offered',
        'brands_serviced',
        'rating',
        'total_interventions',
        'average_cost',
        'average_delay_days',
        'is_preferred',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'services_offered' => 'array',
        'brands_serviced' => 'array',
        'rating' => 'decimal:2',
        'total_interventions' => 'integer',
        'average_cost' => 'decimal:2',
        'average_delay_days' => 'integer',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the maintenances
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Update rating based on maintenances
     */
    public function updateRating(): void
    {
        $completedMaintenances = $this->maintenances()
            ->where('status', 'completed')
            ->get();

        if ($completedMaintenances->isEmpty()) {
            return;
        }

        $this->total_interventions = $completedMaintenances->count();
        $this->average_cost = $completedMaintenances->avg('total_cost');

        // Calculate average delay
        $delays = $completedMaintenances->filter(function ($maintenance) {
            return $maintenance->scheduled_date && $maintenance->completed_date;
        })->map(function ($maintenance) {
            return $maintenance->scheduled_date->diffInDays($maintenance->completed_date);
        });

        if ($delays->isNotEmpty()) {
            $this->average_delay_days = $delays->avg();
        }

        $this->save();
    }

    /**
     * Scope for active workshops
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for preferred workshops
     */
    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true)->where('is_active', true);
    }
}
