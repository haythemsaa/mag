<?php

namespace App\Services;

use App\Models\Accident;
use App\Models\DashcamEvent;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\FuelTransaction;
use App\Models\Infraction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DriverScoringService
{
    // Weights for overall score (should total 100%)
    protected const SAFETY_WEIGHT = 0.35; // 35%
    protected const EFFICIENCY_WEIGHT = 0.25; // 25%
    protected const COMPLIANCE_WEIGHT = 0.20; // 20%
    protected const BEHAVIOR_WEIGHT = 0.20; // 20%

    /**
     * Calculate score for a driver for a specific period
     */
    public function calculateScore(Driver $driver, int $year, int $month): DriverScore
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Get or create score record
        $score = DriverScore::firstOrNew([
            'driver_id' => $driver->id,
            'year' => $year,
            'month' => $month,
        ]);

        $score->organization_id = $driver->organization_id;

        // Gather metrics
        $metrics = $this->gatherMetrics($driver, $startDate, $endDate);

        // Calculate component scores
        $safetyScore = $this->calculateSafetyScore($metrics);
        $efficiencyScore = $this->calculateEfficiencyScore($metrics);
        $complianceScore = $this->calculateComplianceScore($metrics);
        $behaviorScore = $this->calculateBehaviorScore($metrics);

        // Calculate total score
        $totalScore = (
            $safetyScore * self::SAFETY_WEIGHT +
            $efficiencyScore * self::EFFICIENCY_WEIGHT +
            $complianceScore * self::COMPLIANCE_WEIGHT +
            $behaviorScore * self::BEHAVIOR_WEIGHT
        );

        // Store scores
        $score->safety_score = round($safetyScore, 2);
        $score->efficiency_score = round($efficiencyScore, 2);
        $score->compliance_score = round($complianceScore, 2);
        $score->behavior_score = round($behaviorScore, 2);
        $score->total_score = round($totalScore, 2);

        // Store metrics
        $score->fill($metrics);

        // Calculate trend
        $this->calculateTrend($score);

        // Calculate rank
        $this->calculateRank($score);

        // Store calculation details
        $score->calculation_details = [
            'weights' => [
                'safety' => self::SAFETY_WEIGHT,
                'efficiency' => self::EFFICIENCY_WEIGHT,
                'compliance' => self::COMPLIANCE_WEIGHT,
                'behavior' => self::BEHAVIOR_WEIGHT,
            ],
            'metrics' => $metrics,
        ];

        $score->calculated_at = now();
        $score->save();

        return $score;
    }

    /**
     * Calculate scores for all drivers in an organization
     */
    public function calculateOrganizationScores(int $organizationId, int $year, int $month): Collection
    {
        $drivers = Driver::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $scores = collect();

        foreach ($drivers as $driver) {
            $scores->push($this->calculateScore($driver, $year, $month));
        }

        return $scores;
    }

    /**
     * Gather all metrics for score calculation
     */
    protected function gatherMetrics(Driver $driver, Carbon $startDate, Carbon $endDate): array
    {
        // Safety metrics
        $accidents = Accident::where('driver_id', $driver->id)
            ->whereBetween('accident_date', [$startDate, $endDate])
            ->count();

        $infractions = Infraction::where('driver_id', $driver->id)
            ->whereBetween('infraction_date', [$startDate, $endDate])
            ->count();

        $dashcamEvents = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->count();

        $criticalEvents = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->where('severity', 'critical')
            ->count();

        // Behavior metrics
        $harshBraking = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->where('event_type', 'harsh_braking')
            ->count();

        $harshAcceleration = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->where('event_type', 'harsh_acceleration')
            ->count();

        $harshCornering = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->where('event_type', 'harsh_cornering')
            ->count();

        $speedingEvents = DashcamEvent::where('driver_id', $driver->id)
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->where('event_type', 'speeding')
            ->count();

        // Efficiency metrics (from fuel transactions for driver's vehicle)
        $fuelData = FuelTransaction::where('driver_id', $driver->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_trips,
                SUM(odometer_current - odometer_previous) as total_distance,
                AVG((quantity_liters / NULLIF(odometer_current - odometer_previous, 0)) * 100) as avg_consumption
            ')
            ->first();

        return [
            'total_trips' => $fuelData->total_trips ?? 0,
            'total_distance_km' => round($fuelData->total_distance ?? 0, 2),
            'total_drive_time_hours' => 0, // Would come from GPS data or trip logs
            'accidents_count' => $accidents,
            'infractions_count' => $infractions,
            'dashcam_events_count' => $dashcamEvents,
            'critical_events_count' => $criticalEvents,
            'avg_fuel_consumption' => round($fuelData->avg_consumption ?? 0, 2),
            'idle_time_percent' => 0, // Would come from GPS/telematics data
            'avg_speed_kmh' => 0, // Would come from GPS data
            'harsh_braking_count' => $harshBraking,
            'harsh_acceleration_count' => $harshAcceleration,
            'harsh_cornering_count' => $harshCornering,
            'speeding_events_count' => $speedingEvents,
        ];
    }

    /**
     * Calculate safety score (0-100)
     */
    protected function calculateSafetyScore(array $metrics): float
    {
        $score = 100;

        // Deduct points for accidents
        $score -= ($metrics['accidents_count'] * 20); // -20 per accident

        // Deduct points for critical events
        $score -= ($metrics['critical_events_count'] * 10); // -10 per critical event

        // Deduct points for general dashcam events
        $score -= ($metrics['dashcam_events_count'] * 2); // -2 per event

        // Bonus for no incidents
        if ($metrics['accidents_count'] == 0 && $metrics['critical_events_count'] == 0) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate efficiency score (0-100)
     */
    protected function calculateEfficiencyScore(array $metrics): float
    {
        $score = 100;

        // Score based on fuel consumption
        // Industry average: 8-12 L/100km for light vehicles
        $consumption = $metrics['avg_fuel_consumption'];
        if ($consumption > 0) {
            if ($consumption <= 7) {
                $score = 100; // Excellent
            } elseif ($consumption <= 9) {
                $score = 90; // Good
            } elseif ($consumption <= 11) {
                $score = 75; // Average
            } elseif ($consumption <= 13) {
                $score = 60; // Below average
            } else {
                $score = 40; // Poor
            }
        } else {
            // No data, give neutral score
            $score = 70;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate compliance score (0-100)
     */
    protected function calculateComplianceScore(array $metrics): float
    {
        $score = 100;

        // Deduct points for infractions
        $score -= ($metrics['infractions_count'] * 15); // -15 per infraction

        // Deduct points for speeding events
        $score -= ($metrics['speeding_events_count'] * 5); // -5 per speeding event

        // Bonus for perfect compliance
        if ($metrics['infractions_count'] == 0 && $metrics['speeding_events_count'] == 0) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate behavior score (0-100)
     */
    protected function calculateBehaviorScore(array $metrics): float
    {
        $score = 100;

        // Deduct points for harsh events
        $score -= ($metrics['harsh_braking_count'] * 3); // -3 per harsh braking
        $score -= ($metrics['harsh_acceleration_count'] * 3); // -3 per harsh acceleration
        $score -= ($metrics['harsh_cornering_count'] * 3); // -3 per harsh cornering

        // Bonus for smooth driving
        $totalHarshEvents = $metrics['harsh_braking_count'] +
            $metrics['harsh_acceleration_count'] +
            $metrics['harsh_cornering_count'];

        if ($totalHarshEvents == 0) {
            $score += 15;
        } elseif ($totalHarshEvents <= 5) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate trend compared to previous period
     */
    protected function calculateTrend(DriverScore $currentScore): void
    {
        // Get previous month's score
        $prevMonth = Carbon::create($currentScore->year, $currentScore->month, 1)->subMonth();

        $previousScore = DriverScore::where('driver_id', $currentScore->driver_id)
            ->where('year', $prevMonth->year)
            ->where('month', $prevMonth->month)
            ->first();

        if ($previousScore) {
            $change = $currentScore->total_score - $previousScore->total_score;
            $currentScore->score_change = round($change, 2);

            if ($change > 5) {
                $currentScore->trend = 'improving';
            } elseif ($change < -5) {
                $currentScore->trend = 'declining';
            } else {
                $currentScore->trend = 'stable';
            }
        } else {
            $currentScore->score_change = null;
            $currentScore->trend = null;
        }
    }

    /**
     * Calculate organization rank
     */
    protected function calculateRank(DriverScore $currentScore): void
    {
        // Get all scores for this organization and period
        $scores = DriverScore::where('organization_id', $currentScore->organization_id)
            ->where('year', $currentScore->year)
            ->where('month', $currentScore->month)
            ->orderBy('total_score', 'desc')
            ->get();

        $currentScore->total_drivers_in_org = $scores->count();

        // Find rank
        $rank = 1;
        foreach ($scores as $score) {
            if ($score->total_score > $currentScore->total_score) {
                $rank++;
            } elseif ($score->total_score == $currentScore->total_score && $score->id < $currentScore->id) {
                $rank++;
            }
        }

        $currentScore->organization_rank = $rank;
    }

    /**
     * Get leaderboard for organization
     */
    public function getLeaderboard(int $organizationId, int $year, int $month, int $limit = 10): Collection
    {
        return DriverScore::where('organization_id', $organizationId)
            ->where('year', $year)
            ->where('month', $month)
            ->with('driver')
            ->orderBy('total_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get score history for a driver
     */
    public function getDriverHistory(Driver $driver, int $months = 12): Collection
    {
        $endDate = now();
        $startDate = now()->subMonths($months);

        return DriverScore::where('driver_id', $driver->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('year', '>', $startDate->year)
                    ->orWhere(function ($q) use ($startDate) {
                        $q->where('year', $startDate->year)
                            ->where('month', '>=', $startDate->month);
                    });
            })
            ->where(function ($query) use ($endDate) {
                $query->where('year', '<', $endDate->year)
                    ->orWhere(function ($q) use ($endDate) {
                        $q->where('year', $endDate->year)
                            ->where('month', '<=', $endDate->month);
                    });
            })
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }
}
