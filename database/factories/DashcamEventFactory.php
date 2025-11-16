<?php

namespace Database\Factories;

use App\Models\DashcamEvent;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DashcamEvent>
 */
class DashcamEventFactory extends Factory
{
    protected $model = DashcamEvent::class;

    public function definition(): array
    {
        $organization = Organization::inRandomOrder()->first() ?? Organization::factory()->create();
        $vehicle = Vehicle::where('organization_id', $organization->id)->inRandomOrder()->first();
        $driver = Driver::where('organization_id', $organization->id)->inRandomOrder()->first();

        $eventTimestamp = fake()->dateTimeBetween('-30 days', 'now');
        $speed = fake()->randomFloat(2, 0, 150);
        $speedLimit = fake()->randomElement([50, 70, 90, 110, 130]);

        return [
            'organization_id' => $organization->id,
            'vehicle_id' => $vehicle?->id,
            'driver_id' => $driver?->id,
            'event_type' => fake()->randomElement([
                'harsh_braking',
                'harsh_acceleration',
                'harsh_cornering',
                'speeding',
                'distraction',
                'phone_usage',
            ]),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'pending_review',
            'event_timestamp' => $eventTimestamp,
            'latitude' => 36.8065 + fake()->randomFloat(4, -0.5, 0.5),
            'longitude' => 10.1815 + fake()->randomFloat(4, -0.5, 0.5),
            'speed_kmh' => $speed,
            'speed_limit_kmh' => $speedLimit,
            'max_g_force' => fake()->randomFloat(2, 0.5, 4.0),
            'dashcam_provider' => fake()->randomElement(['lytx', 'mobileye', 'surfsight', 'smartwitness', 'samsara', 'geotab']),
            'external_event_id' => fake()->uuid(),
            'video_url' => fake()->optional(0.8)->url(),
            'video_thumbnail_url' => fake()->optional(0.7)->url(),
            'video_duration_seconds' => fake()->optional(0.8)->numberBetween(10, 60),
            'video_available_until' => now()->addDays(30),
            'metadata' => ['camera' => 'front', 'audio' => true],
            'received_at' => $eventTimestamp,
        ];
    }

    public function harshBraking(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'harsh_braking',
            'g_force_y' => fake()->randomFloat(2, -3.0, -1.5),
            'max_g_force' => fake()->randomFloat(2, 1.5, 3.5),
        ]);
    }

    public function collision(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'collision',
            'severity' => 'critical',
            'max_g_force' => fake()->randomFloat(2, 3.0, 8.0),
        ]);
    }

    public function speeding(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'speeding',
            'speed_kmh' => fake()->randomFloat(2, 100, 180),
            'speed_limit_kmh' => fake()->randomElement([50, 70, 90, 110]),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending_review']);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 7)),
            'review_notes' => fake()->sentence(),
        ]);
    }

    public function coachingRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'coaching_required',
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => 'critical']);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(function (array $attributes) use ($organization) {
            $vehicle = Vehicle::where('organization_id', $organization->id)->inRandomOrder()->first();
            $driver = Driver::where('organization_id', $organization->id)->inRandomOrder()->first();

            return [
                'organization_id' => $organization->id,
                'vehicle_id' => $vehicle?->id,
                'driver_id' => $driver?->id,
            ];
        });
    }
}
