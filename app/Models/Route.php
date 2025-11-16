<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'route_number',
        'organization_id',
        'vehicle_id',
        'driver_id',
        'name',
        'description',
        'status',
        'planned_date',
        'started_at',
        'completed_at',
        'planned_distance_km',
        'actual_distance_km',
        'distance_variance_km',
        'distance_variance_percent',
        'planned_duration_minutes',
        'actual_duration_minutes',
        'duration_variance_minutes',
        'duration_variance_percent',
        'estimated_fuel_cost',
        'actual_fuel_cost',
        'estimated_total_cost',
        'actual_total_cost',
        'optimization_method',
        'is_optimized',
        'stops_count',
        'completed_stops_count',
        'fuel_consumption_liters',
        'average_speed_kmh',
        'idle_time_minutes',
        'break_time_minutes',
        'optimization_params',
        'waypoints',
        'notes',
        'completion_notes',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'planned_distance_km' => 'decimal:2',
        'actual_distance_km' => 'decimal:2',
        'distance_variance_km' => 'decimal:2',
        'distance_variance_percent' => 'decimal:2',
        'duration_variance_percent' => 'decimal:2',
        'estimated_fuel_cost' => 'decimal:2',
        'actual_fuel_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
        'actual_total_cost' => 'decimal:2',
        'fuel_consumption_liters' => 'decimal:2',
        'average_speed_kmh' => 'decimal:2',
        'is_optimized' => 'boolean',
        'optimization_params' => 'array',
        'waypoints' => 'array',
    ];

    /**
     * Boot method to auto-generate route_number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($route) {
            if (empty($route->route_number)) {
                $route->route_number = static::generateRouteNumber();
            }
        });
    }

    /**
     * Generate unique route number (RT-YYYY-NNNNNN)
     */
    public static function generateRouteNumber(): string
    {
        $year = now()->year;
        $lastRoute = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRoute ? ((int) substr($lastRoute->route_number, -6)) + 1 : 1;

        return 'RT-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_number');
    }

    public function pendingStops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->where('status', 'pending')->orderBy('stop_number');
    }

    public function completedStops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->where('status', 'completed')->orderBy('stop_number');
    }

    /**
     * Scopes
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planned', 'in_progress']);
    }

    public function scopeOptimized($query)
    {
        return $query->where('is_optimized', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('planned_date', $date);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('planned_date', '>=', now()->toDateString())
            ->whereIn('status', ['draft', 'planned']);
    }

    /**
     * Workflow methods
     */
    public function markAsPlanned(): void
    {
        $this->update([
            'status' => 'planned',
        ]);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function complete(array $data = []): void
    {
        // Calculate variances
        $distanceVariance = null;
        $distanceVariancePercent = null;
        $durationVariance = null;
        $durationVariancePercent = null;

        if ($this->planned_distance_km && $this->actual_distance_km) {
            $distanceVariance = $this->actual_distance_km - $this->planned_distance_km;
            $distanceVariancePercent = ($distanceVariance / $this->planned_distance_km) * 100;
        }

        if ($this->planned_duration_minutes && $this->actual_duration_minutes) {
            $durationVariance = $this->actual_duration_minutes - $this->planned_duration_minutes;
            $durationVariancePercent = ($durationVariance / $this->planned_duration_minutes) * 100;
        }

        $this->update(array_merge([
            'status' => 'completed',
            'completed_at' => now(),
            'distance_variance_km' => $distanceVariance,
            'distance_variance_percent' => $distanceVariancePercent,
            'duration_variance_minutes' => $durationVariance,
            'duration_variance_percent' => $durationVariancePercent,
            'completed_stops_count' => $this->stops()->where('status', 'completed')->count(),
        ], $data));
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'completion_notes' => $reason,
        ]);
    }

    public function assignVehicle(int $vehicleId): void
    {
        $this->update([
            'vehicle_id' => $vehicleId,
        ]);
    }

    public function assignDriver(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
        ]);
    }

    /**
     * Route metrics calculation
     */
    public function calculateTotalPlannedDistance(): float
    {
        return $this->stops()->sum('distance_from_previous_km') ?? 0;
    }

    public function calculateTotalPlannedDuration(): int
    {
        $travelTime = $this->stops()->sum('duration_from_previous_minutes') ?? 0;
        $serviceTime = $this->stops()->sum('service_duration_minutes') ?? 0;

        return $travelTime + $serviceTime;
    }

    public function updatePlannedMetrics(): void
    {
        $this->update([
            'planned_distance_km' => $this->calculateTotalPlannedDistance(),
            'planned_duration_minutes' => $this->calculateTotalPlannedDuration(),
            'stops_count' => $this->stops()->count(),
        ]);
    }

    public function getProgressPercentage(): float
    {
        if ($this->stops_count === 0) {
            return 0;
        }

        return ($this->completed_stops_count / $this->stops_count) * 100;
    }

    public function getEstimatedArrival(): ?\Carbon\Carbon
    {
        if (!$this->started_at || !$this->planned_duration_minutes) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->planned_duration_minutes);
    }

    /**
     * Helper methods
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canStart(): bool
    {
        return in_array($this->status, ['planned']) &&
            $this->vehicle_id !== null &&
            $this->driver_id !== null &&
            $this->stops()->count() > 0;
    }

    public function canComplete(): bool
    {
        return $this->status === 'in_progress';
    }
}
