<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverScore>
 */
class DriverScoreFactory extends Factory
{
    protected $model = DriverScore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $safetyScore = fake()->numberBetween(60, 100);
        $efficiencyScore = fake()->numberBetween(60, 100);
        $complianceScore = fake()->numberBetween(60, 100);
        $behaviorScore = fake()->numberBetween(60, 100);

        // Calculate total score with weights
        $totalScore = (
            $safetyScore * 0.35 +
            $efficiencyScore * 0.25 +
            $complianceScore * 0.20 +
            $behaviorScore * 0.20
        );

        $totalTrips = fake()->numberBetween(10, 200);
        $totalDistance = fake()->numberBetween(500, 5000);

        return [
            'organization_id' => Organization::factory(),
            'driver_id' => Driver::factory(),
            'year' => now()->year,
            'month' => now()->month,

            // Scores
            'total_score' => round($totalScore, 2),
            'safety_score' => round($safetyScore, 2),
            'efficiency_score' => round($efficiencyScore, 2),
            'compliance_score' => round($complianceScore, 2),
            'behavior_score' => round($behaviorScore, 2),

            // Trip metrics
            'total_trips' => $totalTrips,
            'total_distance_km' => round($totalDistance, 2),
            'total_drive_time_hours' => fake()->numberBetween(20, 300),

            // Safety metrics
            'accidents_count' => fake()->numberBetween(0, 3),
            'infractions_count' => fake()->numberBetween(0, 5),
            'dashcam_events_count' => fake()->numberBetween(0, 20),
            'critical_events_count' => fake()->numberBetween(0, 5),

            // Efficiency metrics
            'avg_fuel_consumption' => round(fake()->randomFloat(2, 6, 14), 2),
            'idle_time_percent' => round(fake()->randomFloat(2, 5, 25), 2),
            'avg_speed_kmh' => round(fake()->randomFloat(2, 40, 90), 2),

            // Behavior metrics
            'harsh_braking_count' => fake()->numberBetween(0, 30),
            'harsh_acceleration_count' => fake()->numberBetween(0, 30),
            'harsh_cornering_count' => fake()->numberBetween(0, 25),
            'speeding_events_count' => fake()->numberBetween(0, 15),

            // Trend
            'score_change' => round(fake()->randomFloat(2, -10, 10), 2),
            'trend' => fake()->randomElement(['improving', 'declining', 'stable']),

            // Ranking
            'organization_rank' => fake()->numberBetween(1, 50),
            'total_drivers_in_org' => fake()->numberBetween(10, 100),

            // Metadata
            'calculated_at' => now(),
            'calculation_details' => [
                'weights' => [
                    'safety' => 0.35,
                    'efficiency' => 0.25,
                    'compliance' => 0.20,
                    'behavior' => 0.20,
                ],
            ],
        ];
    }

    /**
     * Excellent score (>= 90)
     */
    public function excellent(): static
    {
        return $this->state(function (array $attributes) {
            $safetyScore = fake()->numberBetween(90, 100);
            $efficiencyScore = fake()->numberBetween(88, 100);
            $complianceScore = fake()->numberBetween(90, 100);
            $behaviorScore = fake()->numberBetween(90, 100);

            $totalScore = (
                $safetyScore * 0.35 +
                $efficiencyScore * 0.25 +
                $complianceScore * 0.20 +
                $behaviorScore * 0.20
            );

            return [
                'total_score' => round($totalScore, 2),
                'safety_score' => round($safetyScore, 2),
                'efficiency_score' => round($efficiencyScore, 2),
                'compliance_score' => round($complianceScore, 2),
                'behavior_score' => round($behaviorScore, 2),
                'accidents_count' => 0,
                'infractions_count' => 0,
                'critical_events_count' => 0,
                'dashcam_events_count' => fake()->numberBetween(0, 5),
                'harsh_braking_count' => fake()->numberBetween(0, 5),
                'harsh_acceleration_count' => fake()->numberBetween(0, 5),
                'harsh_cornering_count' => fake()->numberBetween(0, 5),
                'speeding_events_count' => 0,
                'avg_fuel_consumption' => round(fake()->randomFloat(2, 6, 8), 2),
            ];
        });
    }

    /**
     * Good score (70-89)
     */
    public function good(): static
    {
        return $this->state(function (array $attributes) {
            $safetyScore = fake()->numberBetween(70, 89);
            $efficiencyScore = fake()->numberBetween(70, 89);
            $complianceScore = fake()->numberBetween(70, 89);
            $behaviorScore = fake()->numberBetween(70, 89);

            $totalScore = (
                $safetyScore * 0.35 +
                $efficiencyScore * 0.25 +
                $complianceScore * 0.20 +
                $behaviorScore * 0.20
            );

            return [
                'total_score' => round($totalScore, 2),
                'safety_score' => round($safetyScore, 2),
                'efficiency_score' => round($efficiencyScore, 2),
                'compliance_score' => round($complianceScore, 2),
                'behavior_score' => round($behaviorScore, 2),
                'accidents_count' => fake()->numberBetween(0, 1),
                'infractions_count' => fake()->numberBetween(0, 2),
                'critical_events_count' => fake()->numberBetween(0, 2),
                'dashcam_events_count' => fake()->numberBetween(5, 15),
                'harsh_braking_count' => fake()->numberBetween(5, 15),
                'harsh_acceleration_count' => fake()->numberBetween(5, 15),
                'harsh_cornering_count' => fake()->numberBetween(5, 15),
                'speeding_events_count' => fake()->numberBetween(0, 5),
                'avg_fuel_consumption' => round(fake()->randomFloat(2, 8, 10), 2),
            ];
        });
    }

    /**
     * Average score (60-69)
     */
    public function average(): static
    {
        return $this->state(function (array $attributes) {
            $safetyScore = fake()->numberBetween(60, 69);
            $efficiencyScore = fake()->numberBetween(60, 69);
            $complianceScore = fake()->numberBetween(60, 69);
            $behaviorScore = fake()->numberBetween(60, 69);

            $totalScore = (
                $safetyScore * 0.35 +
                $efficiencyScore * 0.25 +
                $complianceScore * 0.20 +
                $behaviorScore * 0.20
            );

            return [
                'total_score' => round($totalScore, 2),
                'safety_score' => round($safetyScore, 2),
                'efficiency_score' => round($efficiencyScore, 2),
                'compliance_score' => round($complianceScore, 2),
                'behavior_score' => round($behaviorScore, 2),
                'accidents_count' => fake()->numberBetween(1, 2),
                'infractions_count' => fake()->numberBetween(2, 5),
                'critical_events_count' => fake()->numberBetween(1, 3),
                'dashcam_events_count' => fake()->numberBetween(15, 30),
                'harsh_braking_count' => fake()->numberBetween(15, 25),
                'harsh_acceleration_count' => fake()->numberBetween(15, 25),
                'harsh_cornering_count' => fake()->numberBetween(10, 20),
                'speeding_events_count' => fake()->numberBetween(5, 10),
                'avg_fuel_consumption' => round(fake()->randomFloat(2, 10, 12), 2),
            ];
        });
    }

    /**
     * Poor score (< 60)
     */
    public function poor(): static
    {
        return $this->state(function (array $attributes) {
            $safetyScore = fake()->numberBetween(30, 59);
            $efficiencyScore = fake()->numberBetween(30, 59);
            $complianceScore = fake()->numberBetween(30, 59);
            $behaviorScore = fake()->numberBetween(30, 59);

            $totalScore = (
                $safetyScore * 0.35 +
                $efficiencyScore * 0.25 +
                $complianceScore * 0.20 +
                $behaviorScore * 0.20
            );

            return [
                'total_score' => round($totalScore, 2),
                'safety_score' => round($safetyScore, 2),
                'efficiency_score' => round($efficiencyScore, 2),
                'compliance_score' => round($complianceScore, 2),
                'behavior_score' => round($behaviorScore, 2),
                'accidents_count' => fake()->numberBetween(2, 5),
                'infractions_count' => fake()->numberBetween(5, 10),
                'critical_events_count' => fake()->numberBetween(3, 8),
                'dashcam_events_count' => fake()->numberBetween(30, 60),
                'harsh_braking_count' => fake()->numberBetween(25, 50),
                'harsh_acceleration_count' => fake()->numberBetween(25, 50),
                'harsh_cornering_count' => fake()->numberBetween(20, 40),
                'speeding_events_count' => fake()->numberBetween(10, 25),
                'avg_fuel_consumption' => round(fake()->randomFloat(2, 12, 16), 2),
            ];
        });
    }

    /**
     * Improving trend
     */
    public function improving(): static
    {
        return $this->state(fn (array $attributes) => [
            'trend' => 'improving',
            'score_change' => round(fake()->randomFloat(2, 6, 15), 2),
        ]);
    }

    /**
     * Declining trend
     */
    public function declining(): static
    {
        return $this->state(fn (array $attributes) => [
            'trend' => 'declining',
            'score_change' => round(fake()->randomFloat(2, -15, -6), 2),
        ]);
    }

    /**
     * Stable trend
     */
    public function stable(): static
    {
        return $this->state(fn (array $attributes) => [
            'trend' => 'stable',
            'score_change' => round(fake()->randomFloat(2, -5, 5), 2),
        ]);
    }

    /**
     * Current month
     */
    public function currentMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => now()->year,
            'month' => now()->month,
        ]);
    }

    /**
     * Last month
     */
    public function lastMonth(): static
    {
        $lastMonth = now()->subMonth();

        return $this->state(fn (array $attributes) => [
            'year' => $lastMonth->year,
            'month' => $lastMonth->month,
        ]);
    }

    /**
     * Specific period
     */
    public function forPeriod(int $year, int $month): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Top performer
     */
    public function topPerformer(): static
    {
        return $this->excellent()->state(fn (array $attributes) => [
            'organization_rank' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Bottom performer
     */
    public function bottomPerformer(): static
    {
        return $this->poor()->state(function (array $attributes) {
            $totalDrivers = $attributes['total_drivers_in_org'] ?? 50;

            return [
                'organization_rank' => fake()->numberBetween($totalDrivers - 5, $totalDrivers),
            ];
        });
    }

    /**
     * Perfect safety record
     */
    public function perfectSafety(): static
    {
        return $this->state(fn (array $attributes) => [
            'safety_score' => 100,
            'accidents_count' => 0,
            'infractions_count' => 0,
            'critical_events_count' => 0,
            'dashcam_events_count' => 0,
        ]);
    }

    /**
     * Efficient driver
     */
    public function efficient(): static
    {
        return $this->state(fn (array $attributes) => [
            'efficiency_score' => fake()->numberBetween(90, 100),
            'avg_fuel_consumption' => round(fake()->randomFloat(2, 6, 7.5), 2),
            'idle_time_percent' => round(fake()->randomFloat(2, 2, 8), 2),
        ]);
    }

    /**
     * Smooth driver (good behavior)
     */
    public function smoothDriver(): static
    {
        return $this->state(fn (array $attributes) => [
            'behavior_score' => fake()->numberBetween(90, 100),
            'harsh_braking_count' => fake()->numberBetween(0, 3),
            'harsh_acceleration_count' => fake()->numberBetween(0, 3),
            'harsh_cornering_count' => fake()->numberBetween(0, 3),
        ]);
    }

    /**
     * Compliant driver
     */
    public function compliant(): static
    {
        return $this->state(fn (array $attributes) => [
            'compliance_score' => fake()->numberBetween(90, 100),
            'infractions_count' => 0,
            'speeding_events_count' => fake()->numberBetween(0, 2),
        ]);
    }

    /**
     * With full metrics
     */
    public function withFullMetrics(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_trips' => fake()->numberBetween(50, 200),
            'total_distance_km' => round(fake()->randomFloat(2, 1000, 5000), 2),
            'total_drive_time_hours' => fake()->numberBetween(80, 320),
        ]);
    }
}
