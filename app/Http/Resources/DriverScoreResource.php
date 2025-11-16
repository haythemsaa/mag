<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Period
            'year' => $this->year,
            'month' => $this->month,
            'period_label' => $this->getPeriodLabel(),

            // Scores
            'total_score' => $this->total_score,
            'safety_score' => $this->safety_score,
            'efficiency_score' => $this->efficiency_score,
            'compliance_score' => $this->compliance_score,
            'behavior_score' => $this->behavior_score,

            // Score metadata
            'grade' => $this->getGrade(),
            'score_color' => $this->getScoreColor(),
            'performance_summary' => $this->getPerformanceSummary(),

            // Component analysis
            'weakest_component' => $this->getWeakestComponent(),
            'strongest_component' => $this->getStrongestComponent(),

            // Trip metrics
            'total_trips' => $this->total_trips,
            'total_distance_km' => $this->total_distance_km,
            'total_drive_time_hours' => $this->total_drive_time_hours,

            // Safety metrics
            'accidents_count' => $this->accidents_count,
            'infractions_count' => $this->infractions_count,
            'dashcam_events_count' => $this->dashcam_events_count,
            'critical_events_count' => $this->critical_events_count,
            'safety_incidents_per_1000_km' => $this->getSafetyIncidentsPer1000Km(),

            // Efficiency metrics
            'avg_fuel_consumption' => $this->avg_fuel_consumption,
            'idle_time_percent' => $this->idle_time_percent,
            'avg_speed_kmh' => $this->avg_speed_kmh,

            // Behavior metrics
            'harsh_braking_count' => $this->harsh_braking_count,
            'harsh_acceleration_count' => $this->harsh_acceleration_count,
            'harsh_cornering_count' => $this->harsh_cornering_count,
            'speeding_events_count' => $this->speeding_events_count,

            // Trend analysis
            'score_change' => $this->score_change,
            'trend' => $this->trend,
            'is_improving' => $this->isImproving(),
            'is_declining' => $this->isDeclining(),

            // Ranking
            'organization_rank' => $this->organization_rank,
            'total_drivers_in_org' => $this->total_drivers_in_org,

            // Performance indicators
            'is_excellent' => $this->isExcellent(),
            'is_good' => $this->isGood(),
            'is_poor' => $this->isPoor(),

            // Improvement suggestions
            'improvement_suggestions' => $this->getImprovementSuggestions(),

            // Driver relationship
            'driver' => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'license_number' => $this->driver?->license_number,
                'employee_number' => $this->driver?->employee_number,
            ],

            // Calculation metadata
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'calculation_details' => $this->when(
                $request->user()?->hasRole(['admin', 'manager']),
                $this->calculation_details
            ),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
