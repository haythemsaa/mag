<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Accident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'accident_number',
        'accident_date',
        'location',
        'latitude',
        'longitude',
        'severity',
        'responsibility',
        'description',
        'police_report',
        'police_report_number',
        'injuries',
        'injured_count',
        'status',
        'estimated_cost',
        'final_cost',
        'insurance_claim_number',
        'insurance_claim_date',
        'insurance_status',
        'notes',
    ];

    protected $casts = [
        'accident_date' => 'datetime',
        'insurance_claim_date' => 'date',
        'police_report' => 'boolean',
        'injuries' => 'boolean',
        'injured_count' => 'integer',
        'estimated_cost' => 'decimal:2',
        'final_cost' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($accident) {
            if (!$accident->accident_number) {
                $accident->accident_number = self::generateAccidentNumber();
            }

            if (!$accident->status) {
                $accident->status = 'declared';
            }
        });
    }

    public static function generateAccidentNumber(): string
    {
        $year = now()->year;
        $lastAccident = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAccident ? ((int) substr($lastAccident->accident_number, -6)) + 1 : 1;

        return 'ACC-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
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

    public function photos(): HasMany
    {
        return $this->hasMany(AccidentPhoto::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeByDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeWithInjuries($query)
    {
        return $query->where('injuries', true);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['declared', 'in_progress', 'expertised']);
    }

    public function getHasPoliceReportAttribute(): bool
    {
        return $this->police_report && !empty($this->police_report_number);
    }

    public function getHasInsuranceClaimAttribute(): bool
    {
        return !empty($this->insurance_claim_number);
    }

    public function getDurationDaysAttribute(): ?int
    {
        if ($this->status === 'closed') {
            return $this->created_at->diffInDays($this->updated_at);
        }

        return $this->created_at->diffInDays(now());
    }

    public function markAsExpertised(float $estimatedCost, ?string $notes = null): void
    {
        $this->update([
            'status' => 'expertised',
            'estimated_cost' => $estimatedCost,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function markAsRepaired(float $finalCost): void
    {
        $this->update([
            'status' => 'repaired',
            'final_cost' => $finalCost,
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
        ]);
    }

    public function fileInsuranceClaim(string $claimNumber): void
    {
        $this->update([
            'insurance_claim_number' => $claimNumber,
            'insurance_claim_date' => now(),
            'insurance_status' => 'pending',
        ]);
    }
}
