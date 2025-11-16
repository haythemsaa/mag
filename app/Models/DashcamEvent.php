<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DashcamEvent extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'driver_id',
        'event_number',
        'event_type',
        'severity',
        'status',
        'event_timestamp',
        'latitude',
        'longitude',
        'address',
        'speed_kmh',
        'speed_limit_kmh',
        'g_force_x',
        'g_force_y',
        'g_force_z',
        'max_g_force',
        'dashcam_provider',
        'external_event_id',
        'video_url',
        'video_thumbnail_url',
        'video_duration_seconds',
        'video_available_until',
        'metadata',
        'device_id',
        'device_serial',
        'firmware_version',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'coaching_completed',
        'coaching_completed_at',
        'coached_by',
        'coaching_notes',
        'is_disputed',
        'dispute_reason',
        'disputed_at',
        'webhook_payload',
        'received_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_timestamp' => 'datetime',
        'video_available_until' => 'datetime',
        'reviewed_at' => 'datetime',
        'coaching_completed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'received_at' => 'datetime',
        'metadata' => 'array',
        'webhook_payload' => 'array',
        'coaching_completed' => 'boolean',
        'is_disputed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DashcamEvent $event) {
            if (empty($event->event_number)) {
                $event->event_number = static::generateEventNumber();
            }
        });
    }

    /**
     * Generate unique event number: DCE-YYYY-NNNNNN
     */
    public static function generateEventNumber(): string
    {
        $year = now()->year;
        $lastEvent = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastEvent
            ? ((int) substr($lastEvent->event_number, -6)) + 1
            : 1;

        return 'DCE-'.$year.'-'.str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the organization that owns the event.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the vehicle involved in the event.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the driver involved in the event.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Get the user who reviewed the event.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who coached the driver.
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coached_by');
    }

    /**
     * Scope events by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope events by severity
     */
    public function scopeWithSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope events by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope events by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('dashcam_provider', $provider);
    }

    /**
     * Scope pending review events
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope reviewed events
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope disputed events
     */
    public function scopeDisputed($query)
    {
        return $query->where('is_disputed', true);
    }

    /**
     * Scope events requiring coaching
     */
    public function scopeRequiringCoaching($query)
    {
        return $query->where('status', 'coaching_required')
            ->where('coaching_completed', false);
    }

    /**
     * Scope events within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope critical events
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope high severity events
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Mark event as reviewed
     */
    public function markAsReviewed(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Mark event as acknowledged
     */
    public function acknowledge(): void
    {
        $this->update([
            'status' => 'acknowledged',
        ]);
    }

    /**
     * Mark event as dismissed
     */
    public function dismiss(int $userId, string $reason): void
    {
        $this->update([
            'status' => 'dismissed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }

    /**
     * Mark event as requiring coaching
     */
    public function requireCoaching(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'coaching_required',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Mark coaching as completed
     */
    public function completeCoaching(int $userId, ?string $notes = null): void
    {
        $this->update([
            'coaching_completed' => true,
            'coaching_completed_at' => now(),
            'coached_by' => $userId,
            'coaching_notes' => $notes,
        ]);
    }

    /**
     * Dispute event
     */
    public function dispute(string $reason): void
    {
        $this->update([
            'status' => 'disputed',
            'is_disputed' => true,
            'dispute_reason' => $reason,
            'disputed_at' => now(),
        ]);
    }

    /**
     * Check if event is pending review
     */
    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    /**
     * Check if event is reviewed
     */
    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    /**
     * Check if event is acknowledged
     */
    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    /**
     * Check if event is disputed
     */
    public function isDisputed(): bool
    {
        return $this->is_disputed;
    }

    /**
     * Check if event requires coaching
     */
    public function requiresCoaching(): bool
    {
        return $this->status === 'coaching_required' && ! $this->coaching_completed;
    }

    /**
     * Check if coaching is completed
     */
    public function isCoachingCompleted(): bool
    {
        return $this->coaching_completed;
    }

    /**
     * Check if video is available
     */
    public function hasVideo(): bool
    {
        if (! $this->video_url) {
            return false;
        }

        if ($this->video_available_until) {
            return now()->lte($this->video_available_until);
        }

        return true;
    }

    /**
     * Check if event exceeds speed limit
     */
    public function isSpeedingEvent(): bool
    {
        if ($this->speed_kmh && $this->speed_limit_kmh) {
            return $this->speed_kmh > $this->speed_limit_kmh;
        }

        return false;
    }

    /**
     * Get speeding amount in km/h
     */
    public function getSpeedingAmount(): ?float
    {
        if ($this->speed_kmh && $this->speed_limit_kmh) {
            return max(0, $this->speed_kmh - $this->speed_limit_kmh);
        }

        return null;
    }

    /**
     * Get event age in days
     */
    public function getEventAge(): int
    {
        return now()->diffInDays($this->event_timestamp);
    }

    /**
     * Check if event is critical based on G-force
     */
    public function isCriticalGForce(): bool
    {
        return $this->max_g_force && $this->max_g_force >= 3.0;
    }

    /**
     * Get event description
     */
    public function getEventDescription(): string
    {
        return match ($this->event_type) {
            'harsh_braking' => 'Harsh Braking',
            'harsh_acceleration' => 'Harsh Acceleration',
            'harsh_cornering' => 'Harsh Cornering',
            'collision' => 'Collision Detected',
            'speeding' => 'Speeding Violation',
            'distraction' => 'Driver Distraction',
            'drowsiness' => 'Driver Drowsiness',
            'phone_usage' => 'Phone Usage While Driving',
            'smoking' => 'Smoking While Driving',
            'no_seatbelt' => 'No Seatbelt Detected',
            'lane_departure' => 'Lane Departure',
            'forward_collision_warning' => 'Forward Collision Warning',
            'tailgating' => 'Tailgating',
            'rolling_stop' => 'Rolling Stop',
            default => 'Other Event',
        };
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }
}
