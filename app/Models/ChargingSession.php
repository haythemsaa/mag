<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ChargingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'vehicle_id', 'driver_id', 'charging_station_id',
        'session_number', 'status', 'start_time', 'end_time', 'duration_minutes',
        'battery_level_start_percent', 'battery_level_end_percent', 'battery_charged_percent',
        'energy_delivered_kwh', 'average_power_kw', 'peak_power_kw',
        'cost_per_kwh', 'connection_fee', 'total_cost',
        'interruption_reason', 'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'energy_delivered_kwh' => 'decimal:2',
        'average_power_kw' => 'decimal:2',
        'peak_power_kw' => 'decimal:2',
        'cost_per_kwh' => 'decimal:4',
        'connection_fee' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->session_number)) {
                $session->session_number = self::generateSessionNumber();
            }
        });
    }

    public static function generateSessionNumber(): string
    {
        $year = now()->year;
        $lastSession = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $sequence = $lastSession ? ((int) substr($lastSession->session_number, -6)) + 1 : 1;
        return 'CHG-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

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

    public function chargingStation(): BelongsTo
    {
        return $this->belongsTo(ChargingStation::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'end_time' => now(),
            'duration_minutes' => now()->diffInMinutes($this->start_time),
        ]);
    }
}
