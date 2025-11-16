<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Route;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Route::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::inRandomOrder()->first() ?? Organization::factory()->create();
        $vehicle = Vehicle::where('organization_id', $organization->id)->inRandomOrder()->first();
        $driver = Driver::where('organization_id', $organization->id)->inRandomOrder()->first();

        $plannedDate = fake()->dateTimeBetween('-30 days', '+60 days');
        $plannedDistanceKm = fake()->randomFloat(2, 10, 500);
        $plannedDurationMinutes = (int) ($plannedDistanceKm / 50 * 60); // Assume 50 km/h average

        return [
            'organization_id' => $organization->id,
            'vehicle_id' => $vehicle?->id,
            'driver_id' => $driver?->id,
            'name' => fake()->randomElement([
                'Downtown Deliveries',
                'North District Service Route',
                'Morning Pickups',
                'Afternoon Deliveries',
                'Customer Visits - Zone A',
                'Weekly Maintenance Tour',
                'Express Route',
                'Standard Delivery Circuit',
            ]),
            'description' => fake()->optional(0.7)->sentence(12),
            'status' => 'draft',
            'planned_date' => $plannedDate,
            'started_at' => null,
            'completed_at' => null,
            'planned_distance_km' => $plannedDistanceKm,
            'actual_distance_km' => null,
            'distance_variance_km' => null,
            'distance_variance_percent' => null,
            'planned_duration_minutes' => $plannedDurationMinutes,
            'actual_duration_minutes' => null,
            'duration_variance_minutes' => null,
            'duration_variance_percent' => null,
            'estimated_fuel_cost' => fake()->randomFloat(2, 15, 200),
            'actual_fuel_cost' => null,
            'estimated_total_cost' => fake()->randomFloat(2, 50, 500),
            'actual_total_cost' => null,
            'optimization_method' => null,
            'is_optimized' => false,
            'stops_count' => 0,
            'completed_stops_count' => 0,
            'fuel_consumption_liters' => null,
            'average_speed_kmh' => null,
            'idle_time_minutes' => null,
            'break_time_minutes' => null,
            'optimization_params' => null,
            'waypoints' => null,
            'notes' => fake()->optional(0.3)->sentence(10),
            'completion_notes' => null,
        ];
    }

    /**
     * Indicate that the route is in draft status
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the route is planned
     */
    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planned',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the route is in progress
     */
    public function inProgress(): static
    {
        $startedAt = fake()->dateTimeBetween('-7 days', 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => $startedAt,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the route is completed
     */
    public function completed(): static
    {
        $startedAt = fake()->dateTimeBetween('-60 days', '-1 day');
        $durationHours = fake()->randomFloat(2, 1, 10);
        $completedAt = (clone $startedAt)->modify("+{$durationHours} hours");

        $plannedDistance = fake()->randomFloat(2, 50, 500);
        $actualDistance = $plannedDistance * fake()->randomFloat(2, 0.9, 1.1);
        $distanceVariance = $actualDistance - $plannedDistance;
        $distanceVariancePercent = ($distanceVariance / $plannedDistance) * 100;

        $plannedDuration = (int) ($plannedDistance / 50 * 60);
        $actualDuration = (int) ($actualDistance / 50 * 60);
        $durationVariance = $actualDuration - $plannedDuration;
        $durationVariancePercent = ($durationVariance / $plannedDuration) * 100;

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'planned_distance_km' => $plannedDistance,
            'actual_distance_km' => $actualDistance,
            'distance_variance_km' => $distanceVariance,
            'distance_variance_percent' => $distanceVariancePercent,
            'planned_duration_minutes' => $plannedDuration,
            'actual_duration_minutes' => $actualDuration,
            'duration_variance_minutes' => $durationVariance,
            'duration_variance_percent' => $durationVariancePercent,
            'fuel_consumption_liters' => fake()->randomFloat(2, 10, 80),
            'average_speed_kmh' => fake()->randomFloat(2, 30, 70),
            'idle_time_minutes' => fake()->numberBetween(10, 120),
            'break_time_minutes' => fake()->numberBetween(15, 60),
            'completion_notes' => fake()->optional(0.5)->sentence(12),
        ]);
    }

    /**
     * Indicate that the route is cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'started_at' => null,
            'completed_at' => null,
            'completion_notes' => fake()->randomElement([
                'Vehicle breakdown',
                'Driver unavailable',
                'Weather conditions',
                'Customer request',
                'Route rescheduled',
            ]),
        ]);
    }

    /**
     * Indicate that the route is optimized with nearest neighbor
     */
    public function optimizedNearestNeighbor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optimized' => true,
            'optimization_method' => 'nearest_neighbor',
            'optimization_params' => [
                'algorithm' => 'nearest_neighbor',
                'optimized_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Indicate that the route is optimized with 2-opt
     */
    public function optimizedTwoOpt(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optimized' => true,
            'optimization_method' => 'two_opt',
            'optimization_params' => [
                'algorithm' => 'two_opt',
                'iterations' => fake()->numberBetween(5, 50),
                'optimized_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Route with vehicle and driver assigned
     */
    public function withAssignments(): static
    {
        return $this->state(function (array $attributes) {
            $organization = Organization::find($attributes['organization_id']);
            $vehicle = Vehicle::where('organization_id', $organization->id)->inRandomOrder()->first()
                ?? Vehicle::factory()->create(['organization_id' => $organization->id]);
            $driver = Driver::where('organization_id', $organization->id)->inRandomOrder()->first()
                ?? Driver::factory()->create(['organization_id' => $organization->id]);

            return [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ];
        });
    }

    /**
     * Route scheduled for today
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'planned_date' => now(),
        ]);
    }

    /**
     * Route scheduled for tomorrow
     */
    public function tomorrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'planned_date' => now()->addDay(),
        ]);
    }

    /**
     * Route scheduled for next week
     */
    public function nextWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'planned_date' => now()->addWeek(),
        ]);
    }

    /**
     * For a specific organization
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }
}
