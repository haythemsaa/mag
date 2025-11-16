<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class TheftAlert extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'geofence_id',
        'alert_number',
        'type',
        'status',
        'severity',
        'description',
        'detected_at',
        'resolved_at',
        'latitude',
        'longitude',
        'address',
        'gps_signal_available',
        'signal_strength',
        'speed_kmh',
        'heading',
        'engine_on',
        'ignition_on',
        'outside_work_hours',
        'geofence_exit_unauthorized',
        'gps_jamming_suspected',
        'towing_movement_detected',
        'speed_anomaly_detected',
        'assigned_to_user_id',
        'assigned_at',
        'investigation_started_at',
        'investigation_notes',
        'resolution_notes',
        'sms_sent',
        'sms_sent_at',
        'email_sent',
        'email_sent_at',
        'push_notification_sent',
        'push_notification_sent_at',
        'police_notified',
        'police_notified_at',
        'police_reference_number',
        'insurance_claim_number',
        'additional_data',
        'admin_notes',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'assigned_at' => 'datetime',
        'investigation_started_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'push_notification_sent_at' => 'datetime',
        'police_notified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed_kmh' => 'decimal:2',
        'heading' => 'decimal:2',
        'gps_signal_available' => 'boolean',
        'engine_on' => 'boolean',
        'ignition_on' => 'boolean',
        'outside_work_hours' => 'boolean',
        'geofence_exit_unauthorized' => 'boolean',
        'gps_jamming_suspected' => 'boolean',
        'towing_movement_detected' => 'boolean',
        'speed_anomaly_detected' => 'boolean',
        'sms_sent' => 'boolean',
        'email_sent' => 'boolean',
        'push_notification_sent' => 'boolean',
        'police_notified' => 'boolean',
        'additional_data' => 'array',
    ];

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($theftAlert) {
            if (!$theftAlert->alert_number) {
                $theftAlert->alert_number = static::generateAlertNumber();
            }

            if (!$theftAlert->detected_at) {
                $theftAlert->detected_at = now();
            }
        });
    }

    /**
     * Generate a unique alert number: TH-YYYY-NNNNNN
     */
    public static function generateAlertNumber(): string
    {
        $year = now()->year;
        $lastAlert = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAlert ? ((int) substr($lastAlert->alert_number, -6)) + 1 : 1;

        return 'TH-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
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

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Scopes
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInvestigating(Builder $query): Builder
    {
        return $query->where('status', 'investigating');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'investigating']);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh(Builder $query): Builder
    {
        return $query->whereIn('severity', ['critical', 'high']);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('detected_at', '>=', now()->subHours($hours));
    }

    public function scopeConfirmedTheft(Builder $query): Builder
    {
        return $query->where('status', 'confirmed_theft');
    }

    /**
     * Helper methods
     */
    public function assign(User $user): void
    {
        $this->update([
            'assigned_to_user_id' => $user->id,
            'assigned_at' => now(),
        ]);
    }

    public function startInvestigation(): void
    {
        $this->update([
            'status' => 'investigating',
            'investigation_started_at' => now(),
        ]);
    }

    public function markAsFalseAlarm(string $notes = null): void
    {
        $this->update([
            'status' => 'false_alarm',
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function confirmTheft(string $notes = null): void
    {
        $this->update([
            'status' => 'confirmed_theft',
            'resolution_notes' => $notes,
        ]);
    }

    public function markAsResolved(string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function notifyPolice(string $referenceNumber = null): void
    {
        $this->update([
            'police_notified' => true,
            'police_notified_at' => now(),
            'police_reference_number' => $referenceNumber,
        ]);
    }

    public function linkInsuranceClaim(string $claimNumber): void
    {
        $this->update([
            'insurance_claim_number' => $claimNumber,
        ]);
    }

    public function recordNotification(string $type): void
    {
        $field = $type . '_sent';
        $timestampField = $type . '_sent_at';

        if (in_array($type, ['sms', 'email', 'push_notification'])) {
            $this->update([
                $field => true,
                $timestampField => now(),
            ]);
        }
    }

    /**
     * Check if alert is resolved
     */
    public function isResolved(): bool
    {
        return in_array($this->status, ['false_alarm', 'resolved']);
    }

    /**
     * Check if alert is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if police has been notified
     */
    public function isPoliceNotified(): bool
    {
        return $this->police_notified;
    }

    /**
     * Get response time in minutes
     */
    public function getResponseTimeMinutes(): ?int
    {
        if (!$this->investigation_started_at) {
            return null;
        }

        return $this->detected_at->diffInMinutes($this->investigation_started_at);
    }

    /**
     * Get resolution time in hours
     */
    public function getResolutionTimeHours(): ?float
    {
        if (!$this->resolved_at) {
            return null;
        }

        return round($this->detected_at->diffInHours($this->resolved_at, true), 2);
    }
}
