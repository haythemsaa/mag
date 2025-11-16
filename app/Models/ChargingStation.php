<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ChargingStation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'site_id', 'name', 'station_code', 'description', 'type',
        'address', 'city', 'postal_code', 'country', 'latitude', 'longitude',
        'connector_type', 'max_power_kw', 'supports_fast_charging', 'number_of_ports',
        'status', 'is_active', 'last_maintenance_date', 'next_maintenance_date',
        'cost_per_kwh', 'connection_fee', 'monthly_subscription',
        'total_sessions', 'total_energy_kwh', 'total_revenue',
        'network_operator', 'network_membership_required', 'access_card_required',
        'requires_reservation', 'max_reservation_minutes', 'access_instructions',
        'operating_hours', 'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'max_power_kw' => 'decimal:2',
        'supports_fast_charging' => 'boolean',
        'is_active' => 'boolean',
        'last_maintenance_date' => 'datetime',
        'next_maintenance_date' => 'datetime',
        'cost_per_kwh' => 'decimal:4',
        'connection_fee' => 'decimal:2',
        'monthly_subscription' => 'decimal:2',
        'total_energy_kwh' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'operating_hours' => 'array',
        'requires_reservation' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }
}
