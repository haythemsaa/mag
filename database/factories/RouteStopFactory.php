<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RouteStop>
 */
class RouteStopFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RouteStop::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $route = Route::inRandomOrder()->first() ?? Route::factory()->create();

        // Tunisia coordinates (Tunis area)
        $baseLatitude = 36.8065 + (fake()->randomFloat(4, -0.5, 0.5));
        $baseLongitude = 10.1815 + (fake()->randomFloat(4, -0.5, 0.5));

        return [
            'route_id' => $route->id,
            'stop_number' => 1,
            'optimized_stop_number' => null,
            'type' => fake()->randomElement(['pickup', 'delivery', 'service', 'visit']),
            'status' => 'pending',

            // Location
            'location_name' => fake()->company(),
            'address' => fake()->address(),
            'latitude' => $baseLatitude,
            'longitude' => $baseLongitude,

            // Contact
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->optional(0.6)->email(),

            // Timing
            'planned_arrival' => null,
            'planned_departure' => null,
            'actual_arrival' => null,
            'actual_departure' => null,
            'service_duration_minutes' => fake()->numberBetween(10, 60),
            'actual_service_duration_minutes' => null,

            // Time window
            'time_window_start' => fake()->optional(0.7)->time('H:i'),
            'time_window_end' => fake()->optional(0.7)->time('H:i'),

            // Distance/duration
            'distance_from_previous_km' => 0,
            'duration_from_previous_minutes' => 0,

            // Tasks
            'instructions' => fake()->optional(0.6)->sentence(15),
            'notes' => fake()->optional(0.3)->sentence(10),
            'requires_signature' => fake()->boolean(40),
            'requires_photo' => fake()->boolean(30),

            // Completion
            'signature_path' => null,
            'photo_paths' => null,
            'completion_notes' => null,
            'failure_reason' => null,

            // Package details
            'reference_number' => fake()->optional(0.8)->bothify('REF-####-????'),
            'package_count' => fake()->optional(0.7)->numberBetween(1, 20),
            'package_weight_kg' => fake()->optional(0.7)->randomFloat(2, 0.5, 500),
            'package_volume_m3' => fake()->optional(0.5)->randomFloat(3, 0.01, 5),

            // Priority
            'priority' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Stop type: Pickup
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pickup',
            'requires_signature' => true,
        ]);
    }

    /**
     * Stop type: Delivery
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'delivery',
            'requires_signature' => true,
            'requires_photo' => fake()->boolean(50),
        ]);
    }

    /**
     * Stop type: Service
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'service',
            'service_duration_minutes' => fake()->numberBetween(30, 120),
            'requires_photo' => true,
        ]);
    }

    /**
     * Stop type: Visit
     */
    public function visit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'visit',
            'service_duration_minutes' => fake()->numberBetween(15, 45),
        ]);
    }

    /**
     * Stop type: Break
     */
    public function break(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'break',
            'location_name' => fake()->randomElement(['Rest Area', 'Lunch Break', 'Coffee Break']),
            'service_duration_minutes' => fake()->numberBetween(15, 60),
            'requires_signature' => false,
            'requires_photo' => false,
            'reference_number' => null,
            'package_count' => null,
        ]);
    }

    /**
     * Status: Pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'actual_arrival' => null,
            'actual_departure' => null,
        ]);
    }

    /**
     * Status: Arrived
     */
    public function arrived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'arrived',
            'actual_arrival' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'actual_departure' => null,
        ]);
    }

    /**
     * Status: In Progress
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'actual_arrival' => now()->subMinutes(fake()->numberBetween(10, 60)),
            'actual_departure' => null,
        ]);
    }

    /**
     * Status: Completed
     */
    public function completed(): static
    {
        $arrivalTime = now()->subMinutes(fake()->numberBetween(60, 300));
        $serviceDuration = fake()->numberBetween(10, 60);
        $departureTime = (clone $arrivalTime)->addMinutes($serviceDuration);

        return $this->state(function (array $attributes) use ($arrivalTime, $departureTime, $serviceDuration) {
            $data = [
                'status' => 'completed',
                'actual_arrival' => $arrivalTime,
                'actual_departure' => $departureTime,
                'actual_service_duration_minutes' => $serviceDuration,
                'completion_notes' => fake()->optional(0.4)->sentence(10),
            ];

            // Add signature if required
            if ($attributes['requires_signature'] ?? false) {
                $data['signature_path'] = 'route_stops/signatures/' . fake()->uuid() . '.png';
            }

            // Add photos if required
            if ($attributes['requires_photo'] ?? false) {
                $data['photo_paths'] = [
                    'route_stops/photos/' . fake()->uuid() . '.jpg',
                    'route_stops/photos/' . fake()->uuid() . '.jpg',
                ];
            }

            return $data;
        });
    }

    /**
     * Status: Skipped
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'failure_reason' => fake()->randomElement([
                'Customer not available',
                'Access denied',
                'Time constraints',
                'Customer request',
            ]),
        ]);
    }

    /**
     * Status: Failed
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'actual_arrival' => now()->subMinutes(fake()->numberBetween(30, 120)),
            'failure_reason' => fake()->randomElement([
                'Wrong address',
                'Customer refused delivery',
                'No parking available',
                'Building closed',
                'Incorrect package',
            ]),
        ]);
    }

    /**
     * High priority stop
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(8, 10),
        ]);
    }

    /**
     * Low priority stop
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * With time window constraint
     */
    public function withTimeWindow(): static
    {
        $startHour = fake()->numberBetween(8, 16);
        $endHour = $startHour + fake()->numberBetween(2, 4);

        return $this->state(fn (array $attributes) => [
            'time_window_start' => sprintf('%02d:00', $startHour),
            'time_window_end' => sprintf('%02d:00', min($endHour, 18)),
        ]);
    }

    /**
     * With packages
     */
    public function withPackages(): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_number' => fake()->bothify('REF-####-????'),
            'package_count' => fake()->numberBetween(1, 50),
            'package_weight_kg' => fake()->randomFloat(2, 1, 1000),
            'package_volume_m3' => fake()->randomFloat(3, 0.1, 10),
        ]);
    }

    /**
     * Late arrival
     */
    public function late(): static
    {
        $plannedArrival = now()->subMinutes(30);
        $actualArrival = now();

        return $this->state(fn (array $attributes) => [
            'planned_arrival' => $plannedArrival,
            'actual_arrival' => $actualArrival,
        ]);
    }

    /**
     * For a specific route
     */
    public function forRoute(Route $route): static
    {
        return $this->state(fn (array $attributes) => [
            'route_id' => $route->id,
        ]);
    }

    /**
     * With specific stop number
     */
    public function withStopNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'stop_number' => $number,
        ]);
    }
}
