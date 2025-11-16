<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MaintenancePrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'vehicle_id',
        'prediction_number',
        'prediction_type',
        'title',
        'description',
        'confidence',
        'priority',
        'status',
        'predicted_date',
        'days_until_due',
        'recommended_action_by',
        'current_odometer_km',
        'predicted_odometer_km',
        'estimated_cost_min',
        'estimated_cost_max',
        'estimated_cost_avg',
        'algorithm_used',
        'algorithm_params',
        'historical_data_summary',
        'recommended_actions',
        'preventive_measures',
        'related_maintenance_id',
        'related_contract_id',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'acknowledgment_notes',
        'scheduled_maintenance_id',
        'scheduled_at',
        'completed_at',
        'dismissed_at',
        'dismissed_by_user_id',
        'dismissal_reason',
        'was_accurate',
        'accuracy_variance_days',
        'accuracy_notes',
    ];

    protected $casts = [
        'predicted_date' => 'datetime',
        'recommended_action_by' => 'datetime',
        'acknowledged_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'algorithm_params' => 'array',
        'historical_data_summary' => 'array',
        'recommended_actions' => 'array',
        'preventive_measures' => 'array',
        'was_accurate' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($prediction) {
            if (empty($prediction->prediction_number)) {
                $prediction->prediction_number = static::generatePredictionNumber();
            }

            // Calculate days until due if predicted_date is set
            if ($prediction->predicted_date) {
                $prediction->days_until_due = now()->diffInDays($prediction->predicted_date, false);
            }
        });
    }

    /**
     * Generate unique prediction number: PRED-YYYY-NNNNNN
     */
    public static function generatePredictionNumber(): string
    {
        $year = now()->year;
        $prefix = "PRED-{$year}-";

        $lastPrediction = static::where('prediction_number', 'like', $prefix.'%')
            ->orderBy('prediction_number', 'desc')
            ->first();

        if ($lastPrediction) {
            $lastNumber = (int) substr($lastPrediction->prediction_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the organization that owns the prediction.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the vehicle this prediction is for.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get related maintenance record.
     */
    public function relatedMaintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class, 'related_maintenance_id');
    }

    /**
     * Get related contract.
     */
    public function relatedContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'related_contract_id');
    }

    /**
     * Get user who acknowledged the prediction.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    /**
     * Get scheduled maintenance.
     */
    public function scheduledMaintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class, 'scheduled_maintenance_id');
    }

    /**
     * Get user who dismissed the prediction.
     */
    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by_user_id');
    }

    /**
     * Acknowledge the prediction
     */
    public function acknowledge(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by_user_id' => $user->id,
            'acknowledged_at' => now(),
            'acknowledgment_notes' => $notes,
        ]);
    }

    /**
     * Schedule maintenance for this prediction
     */
    public function scheduleMaintenance(Maintenance $maintenance): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_maintenance_id' => $maintenance->id,
            'scheduled_at' => now(),
        ]);
    }

    /**
     * Mark prediction as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Dismiss the prediction
     */
    public function dismiss(User $user, string $reason): void
    {
        $this->update([
            'status' => 'dismissed',
            'dismissed_by_user_id' => $user->id,
            'dismissed_at' => now(),
            'dismissal_reason' => $reason,
        ]);
    }

    /**
     * Check if prediction is overdue
     */
    public function isOverdue(): bool
    {
        return $this->predicted_date && $this->predicted_date->isPast() &&
               !in_array($this->status, ['completed', 'dismissed']);
    }

    /**
     * Check if prediction is due soon (within 7 days)
     */
    public function isDueSoon(): bool
    {
        return $this->predicted_date &&
               $this->predicted_date->isFuture() &&
               $this->predicted_date->diffInDays(now()) <= 7 &&
               !in_array($this->status, ['completed', 'dismissed']);
    }

    /**
     * Check if prediction is critical priority
     */
    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }

    /**
     * Check if prediction is high confidence
     */
    public function isHighConfidence(): bool
    {
        return in_array($this->confidence, ['high', 'very_high']);
    }

    /**
     * Get prediction urgency level
     */
    public function getUrgencyLevel(): string
    {
        if ($this->isOverdue()) {
            return 'overdue';
        }

        if ($this->isCritical() && $this->isDueSoon()) {
            return 'urgent';
        }

        if ($this->isDueSoon()) {
            return 'soon';
        }

        return 'normal';
    }

    /**
     * Get prediction type label
     */
    public function getTypeLabel(): string
    {
        return match ($this->prediction_type) {
            'maintenance_due' => 'Maintenance Due',
            'part_failure' => 'Part Failure Risk',
            'cost_overrun' => 'Cost Overrun Risk',
            'fuel_efficiency_drop' => 'Fuel Efficiency Drop',
            'battery_degradation' => 'Battery Degradation',
            'tire_replacement' => 'Tire Replacement Needed',
            'brake_wear' => 'Brake Wear',
            'oil_change' => 'Oil Change Due',
            'inspection_due' => 'Inspection Due',
            'contract_expiry' => 'Contract Expiring',
            default => 'Unknown',
        };
    }

    /**
     * Get confidence color for UI
     */
    public function getConfidenceColor(): string
    {
        return match ($this->confidence) {
            'very_high' => 'green',
            'high' => 'blue',
            'medium' => 'yellow',
            'low' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get estimated cost range string
     */
    public function getEstimatedCostRange(): ?string
    {
        if (!$this->estimated_cost_min && !$this->estimated_cost_max) {
            return null;
        }

        if ($this->estimated_cost_min && $this->estimated_cost_max) {
            return number_format($this->estimated_cost_min, 2).' - '.number_format($this->estimated_cost_max, 2).' EUR';
        }

        if ($this->estimated_cost_avg) {
            return '~'.number_format($this->estimated_cost_avg, 2).' EUR';
        }

        return null;
    }

    /**
     * Scope for pending predictions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for acknowledged predictions
     */
    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    /**
     * Scope for scheduled predictions
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope for overdue predictions
     */
    public function scopeOverdue($query)
    {
        return $query->where('predicted_date', '<', now())
            ->whereNotIn('status', ['completed', 'dismissed']);
    }

    /**
     * Scope for due soon (within days)
     */
    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('predicted_date', '>', now())
            ->where('predicted_date', '<=', now()->addDays($days))
            ->whereNotIn('status', ['completed', 'dismissed']);
    }

    /**
     * Scope for critical priority
     */
    public function scopeCritical($query)
    {
        return $query->where('priority', 'critical');
    }

    /**
     * Scope for high confidence
     */
    public function scopeHighConfidence($query)
    {
        return $query->whereIn('confidence', ['high', 'very_high']);
    }

    /**
     * Scope by prediction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('prediction_type', $type);
    }

    /**
     * Scope by vehicle
     */
    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }
}
