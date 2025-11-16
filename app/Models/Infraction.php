<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Infraction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'infraction_number',
        'reference_number',
        'type',
        'infraction_date',
        'infraction_time',
        'location',
        'latitude',
        'longitude',
        'amount',
        'reduced_amount',
        'increased_amount',
        'points_deducted',
        'status',
        'due_date',
        'reduced_due_date',
        'paid_date',
        'contested_date',
        'recorded_speed',
        'speed_limit',
        'payment_method',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'infraction_date' => 'date',
        'infraction_time' => 'datetime:H:i',
        'due_date' => 'date',
        'reduced_due_date' => 'date',
        'paid_date' => 'date',
        'contested_date' => 'date',
        'amount' => 'decimal:2',
        'reduced_amount' => 'decimal:2',
        'increased_amount' => 'decimal:2',
        'points_deducted' => 'integer',
        'recorded_speed' => 'decimal:2',
        'speed_limit' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($infraction) {
            if (!$infraction->infraction_number) {
                $infraction->infraction_number = self::generateInfractionNumber();
            }

            if (!$infraction->status) {
                $infraction->status = 'received';
            }
        });
    }

    public static function generateInfractionNumber(): string
    {
        $year = now()->year;
        $lastInfraction = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInfraction ? ((int) substr($lastInfraction->infraction_number, -6)) + 1 : 1;

        return 'INF-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
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

    public function attachments(): HasMany
    {
        return $this->hasMany(InfractionAttachment::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'pending', 'assigned']);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['received', 'pending', 'assigned'])
            ->whereNull('paid_date');
    }

    public function scopeOverdue($query)
    {
        return $query->unpaid()
            ->where('due_date', '<', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeByVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'cancelled'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date || $this->status === 'paid') {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    public function getAmountToPayAttribute(): float
    {
        if ($this->is_overdue && $this->increased_amount) {
            return (float) $this->increased_amount;
        }

        if ($this->reduced_due_date
            && now()->lte($this->reduced_due_date)
            && $this->reduced_amount
        ) {
            return (float) $this->reduced_amount;
        }

        return (float) $this->amount;
    }

    public function getSpeedExcessAttribute(): ?float
    {
        if (!$this->recorded_speed || !$this->speed_limit) {
            return null;
        }

        return $this->recorded_speed - $this->speed_limit;
    }

    public function markAsPaid(string $paymentMethod, ?string $paymentReference = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_date' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
        ]);
    }

    public function contest(?string $notes = null): void
    {
        $this->update([
            'status' => 'contested',
            'contested_date' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function assignToDriver(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
            'status' => 'assigned',
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ?? $this->notes,
        ]);
    }
}
