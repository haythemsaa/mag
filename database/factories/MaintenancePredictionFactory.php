<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Maintenance;
use App\Models\MaintenancePrediction;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenancePrediction>
 */
class MaintenancePredictionFactory extends Factory
{
    protected $model = MaintenancePrediction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $predictionType = fake()->randomElement([
            'maintenance_due',
            'part_failure',
            'cost_overrun',
            'fuel_efficiency_drop',
            'tire_replacement',
            'oil_change',
            'inspection_due',
        ]);

        $daysUntilDue = fake()->numberBetween(-7, 60);
        $predictedDate = now()->addDays($daysUntilDue);

        return [
            'organization_id' => Organization::factory(),
            'vehicle_id' => Vehicle::factory(),
            'prediction_type' => $predictionType,
            'title' => $this->getTitleForType($predictionType),
            'description' => fake()->sentence(12),
            'confidence' => fake()->randomElement(['low', 'medium', 'high', 'very_high']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => fake()->randomElement(['pending', 'acknowledged', 'scheduled']),
            'predicted_date' => $predictedDate,
            'days_until_due' => $daysUntilDue,
            'recommended_action_by' => $predictedDate->copy()->subDays(7),
            'current_odometer_km' => fake()->numberBetween(50000, 200000),
            'predicted_odometer_km' => fake()->numberBetween(50000, 200000),
            'estimated_cost_min' => round(fake()->randomFloat(2, 100, 500), 2),
            'estimated_cost_max' => round(fake()->randomFloat(2, 500, 2000), 2),
            'estimated_cost_avg' => round(fake()->randomFloat(2, 300, 1000), 2),
            'algorithm_used' => fake()->randomElement(['time_based', 'mileage_based', 'pattern_matching', 'trend_analysis']),
            'algorithm_params' => [
                'data_points' => fake()->numberBetween(5, 50),
                'confidence_threshold' => fake()->randomFloat(2, 0.6, 0.95),
            ],
            'historical_data_summary' => [
                'records_analyzed' => fake()->numberBetween(10, 100),
                'time_period_days' => fake()->numberBetween(90, 365),
            ],
            'recommended_actions' => [
                'Schedule maintenance appointment',
                'Order replacement parts',
                'Verify vehicle condition',
            ],
        ];
    }

    /**
     * Get title for prediction type
     */
    protected function getTitleForType(string $type): string
    {
        return match ($type) {
            'maintenance_due' => 'Preventive Maintenance Due',
            'part_failure' => 'Part Failure Risk Detected',
            'cost_overrun' => 'Cost Overrun Risk',
            'fuel_efficiency_drop' => 'Fuel Efficiency Degradation',
            'battery_degradation' => 'Battery Health Declining',
            'tire_replacement' => 'Tire Replacement Recommended',
            'brake_wear' => 'Brake System Attention Needed',
            'oil_change' => 'Oil Change Due',
            'inspection_due' => 'Inspection Due',
            'contract_expiry' => 'Contract Expiring Soon',
            default => 'Vehicle Attention Required',
        };
    }

    /**
     * Pending status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'acknowledged_at' => null,
            'acknowledged_by_user_id' => null,
            'scheduled_at' => null,
            'completed_at' => null,
            'dismissed_at' => null,
        ]);
    }

    /**
     * Acknowledged status
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'acknowledged',
            'acknowledged_by_user_id' => User::factory(),
            'acknowledged_at' => now()->subDays(fake()->numberBetween(1, 5)),
            'acknowledgment_notes' => fake()->sentence(),
        ]);
    }

    /**
     * Scheduled status
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'acknowledged_by_user_id' => User::factory(),
            'acknowledged_at' => now()->subDays(fake()->numberBetween(2, 10)),
            'scheduled_maintenance_id' => Maintenance::factory(),
            'scheduled_at' => now()->subDays(fake()->numberBetween(1, 5)),
        ]);
    }

    /**
     * Completed status
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'acknowledged_by_user_id' => User::factory(),
            'acknowledged_at' => now()->subDays(fake()->numberBetween(10, 30)),
            'scheduled_maintenance_id' => Maintenance::factory(),
            'scheduled_at' => now()->subDays(fake()->numberBetween(5, 20)),
            'completed_at' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    /**
     * Dismissed status
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dismissed',
            'dismissed_by_user_id' => User::factory(),
            'dismissed_at' => now()->subDays(fake()->numberBetween(1, 10)),
            'dismissal_reason' => fake()->randomElement([
                'Already addressed',
                'False positive',
                'Not applicable',
                'Maintenance completed elsewhere',
            ]),
        ]);
    }

    /**
     * Critical priority
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
            'confidence' => fake()->randomElement(['high', 'very_high']),
            'predicted_date' => now()->addDays(fake()->numberBetween(1, 7)),
        ]);
    }

    /**
     * High priority
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'predicted_date' => now()->addDays(fake()->numberBetween(7, 14)),
        ]);
    }

    /**
     * Low priority
     */
    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
            'predicted_date' => now()->addDays(fake()->numberBetween(30, 90)),
        ]);
    }

    /**
     * High confidence
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomElement(['high', 'very_high']),
        ]);
    }

    /**
     * Low confidence
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomElement(['low', 'medium']),
        ]);
    }

    /**
     * Overdue
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'predicted_date' => now()->subDays(fake()->numberBetween(1, 30)),
            'status' => fake()->randomElement(['pending', 'acknowledged']),
            'priority' => fake()->randomElement(['high', 'critical']),
        ]);
    }

    /**
     * Due soon
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'predicted_date' => now()->addDays(fake()->numberBetween(1, 7)),
            'status' => 'pending',
            'priority' => fake()->randomElement(['medium', 'high']),
        ]);
    }

    /**
     * Maintenance type
     */
    public function maintenanceDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'maintenance_due',
            'title' => 'Preventive Maintenance Due',
            'estimated_cost_min' => 200,
            'estimated_cost_max' => 600,
            'estimated_cost_avg' => 400,
        ]);
    }

    /**
     * Part failure type
     */
    public function partFailure(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'part_failure',
            'title' => 'Part Failure Risk Detected',
            'priority' => fake()->randomElement(['high', 'critical']),
            'estimated_cost_min' => 500,
            'estimated_cost_max' => 2000,
            'estimated_cost_avg' => 1200,
        ]);
    }

    /**
     * Cost overrun type
     */
    public function costOverrun(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'cost_overrun',
            'title' => 'Cost Overrun Risk',
            'estimated_cost_min' => 1000,
            'estimated_cost_max' => 5000,
            'estimated_cost_avg' => 3000,
        ]);
    }

    /**
     * Fuel efficiency type
     */
    public function fuelEfficiencyDrop(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'fuel_efficiency_drop',
            'title' => 'Fuel Efficiency Degradation',
            'recommended_actions' => [
                'Check tire pressure',
                'Inspect air filter',
                'Check for engine issues',
            ],
        ]);
    }

    /**
     * Contract expiry type
     */
    public function contractExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'prediction_type' => 'contract_expiry',
            'title' => 'Contract Expiring Soon',
            'related_contract_id' => Contract::factory(),
            'confidence' => 'very_high',
            'priority' => 'high',
        ]);
    }

    /**
     * With preventive measures
     */
    public function withPreventiveMeasures(): static
    {
        return $this->state(fn (array $attributes) => [
            'preventive_measures' => [
                'Regular inspections',
                'Proper maintenance schedule',
                'Quality replacement parts',
                'Driver training',
            ],
        ]);
    }
}
