<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'driver_id',
        'year',
        'month',
        'total_score',
        'safety_score',
        'efficiency_score',
        'compliance_score',
        'behavior_score',
        'total_trips',
        'total_distance_km',
        'total_drive_time_hours',
        'accidents_count',
        'infractions_count',
        'dashcam_events_count',
        'critical_events_count',
        'avg_fuel_consumption',
        'idle_time_percent',
        'avg_speed_kmh',
        'harsh_braking_count',
        'harsh_acceleration_count',
        'harsh_cornering_count',
        'speeding_events_count',
        'score_change',
        'trend',
        'organization_rank',
        'total_drivers_in_org',
        'calculated_at',
        'calculation_details',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'calculation_details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that owns the score.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the driver this score belongs to.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Scope for a specific period
     */
    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * Scope for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->where('year', now()->year)
            ->where('month', now()->month);
    }

    /**
     * Scope for last month
     */
    public function scopeLastMonth($query)
    {
        $lastMonth = now()->subMonth();

        return $query->where('year', $lastMonth->year)
            ->where('month', $lastMonth->month);
    }

    /**
     * Scope by year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope top performers
     */
    public function scopeTopPerformers($query, int $limit = 10)
    {
        return $query->orderBy('total_score', 'desc')->limit($limit);
    }

    /**
     * Scope bottom performers
     */
    public function scopeBottomPerformers($query, int $limit = 10)
    {
        return $query->orderBy('total_score', 'asc')->limit($limit);
    }

    /**
     * Scope improving drivers
     */
    public function scopeImproving($query)
    {
        return $query->where('trend', 'improving');
    }

    /**
     * Scope declining drivers
     */
    public function scopeDeclining($query)
    {
        return $query->where('trend', 'declining');
    }

    /**
     * Get period label
     */
    public function getPeriodLabel(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    /**
     * Get score grade (A, B, C, D, F)
     */
    public function getGrade(): string
    {
        $score = $this->total_score;

        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * Get score color for UI
     */
    public function getScoreColor(): string
    {
        $score = $this->total_score;

        return match (true) {
            $score >= 80 => 'green',
            $score >= 60 => 'yellow',
            default => 'red',
        };
    }

    /**
     * Check if score is excellent
     */
    public function isExcellent(): bool
    {
        return $this->total_score >= 90;
    }

    /**
     * Check if score is good
     */
    public function isGood(): bool
    {
        return $this->total_score >= 70 && $this->total_score < 90;
    }

    /**
     * Check if score is poor
     */
    public function isPoor(): bool
    {
        return $this->total_score < 60;
    }

    /**
     * Check if driver is improving
     */
    public function isImproving(): bool
    {
        return $this->trend === 'improving';
    }

    /**
     * Check if driver is declining
     */
    public function isDeclining(): bool
    {
        return $this->trend === 'declining';
    }

    /**
     * Get weakest component
     */
    public function getWeakestComponent(): array
    {
        $components = [
            'safety' => $this->safety_score,
            'efficiency' => $this->efficiency_score,
            'compliance' => $this->compliance_score,
            'behavior' => $this->behavior_score,
        ];

        asort($components);
        $weakest = array_key_first($components);

        return [
            'component' => $weakest,
            'score' => $components[$weakest],
        ];
    }

    /**
     * Get strongest component
     */
    public function getStrongestComponent(): array
    {
        $components = [
            'safety' => $this->safety_score,
            'efficiency' => $this->efficiency_score,
            'compliance' => $this->compliance_score,
            'behavior' => $this->behavior_score,
        ];

        arsort($components);
        $strongest = array_key_first($components);

        return [
            'component' => $strongest,
            'score' => $components[$strongest],
        ];
    }

    /**
     * Get improvement suggestions
     */
    public function getImprovementSuggestions(): array
    {
        $suggestions = [];

        if ($this->safety_score < 70) {
            $suggestions[] = 'Focus on defensive driving and accident prevention';
        }

        if ($this->efficiency_score < 70) {
            $suggestions[] = 'Reduce idle time and optimize fuel consumption';
        }

        if ($this->compliance_score < 70) {
            $suggestions[] = 'Follow speed limits and traffic regulations';
        }

        if ($this->behavior_score < 70) {
            $suggestions[] = 'Practice smooth acceleration and braking';
        }

        if ($this->harsh_braking_count > 10) {
            $suggestions[] = 'Anticipate traffic flow to reduce harsh braking';
        }

        if ($this->speeding_events_count > 5) {
            $suggestions[] = 'Maintain speed limits consistently';
        }

        return $suggestions;
    }

    /**
     * Calculate safety incidents per 1000 km
     */
    public function getSafetyIncidentsPer1000Km(): float
    {
        if ($this->total_distance_km == 0) {
            return 0;
        }

        $totalIncidents = $this->accidents_count + $this->critical_events_count;

        return round(($totalIncidents / $this->total_distance_km) * 1000, 2);
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): string
    {
        $grade = $this->getGrade();
        $trend = $this->trend ?? 'stable';

        return match ($grade) {
            'A' => "Excellent driver, {$trend} performance",
            'B' => "Good driver, {$trend} performance",
            'C' => "Average driver, {$trend} performance",
            'D' => "Below average, needs improvement ({$trend})",
            'F' => "Poor performance, immediate coaching required ({$trend})",
            default => "Performance data insufficient",
        };
    }
}
