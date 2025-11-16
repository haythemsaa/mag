<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Accident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accident>
 */
class AccidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $severities = ['minor', 'moderate', 'severe', 'total_loss'];
        $responsibilities = ['driver', 'third_party', 'shared', 'unknown'];
        $statuses = ['declared', 'in_progress', 'expertised', 'repaired', 'closed'];
        $insuranceStatuses = ['pending', 'accepted', 'rejected', 'partially_accepted'];

        return [
            'organization_id' => Organization::factory(),
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'accident_number' => 'ACC-' . now()->year . '-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'accident_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'location' => fake()->streetAddress() . ', ' . fake()->city(),
            'latitude' => fake()->latitude(41, 51), // France coordinates
            'longitude' => fake()->longitude(-5, 10),
            'severity' => fake()->randomElement($severities),
            'responsibility' => fake()->randomElement($responsibilities),
            'status' => fake()->randomElement($statuses),
            'description' => fake()->paragraph(),
            'police_report' => fake()->boolean(60),
            'police_report_number' => fake()->optional()->bothify('PV-#####-????'),
            'injuries' => fake()->boolean(20),
            'injured_count' => fake()->numberBetween(0, 5),
            'third_party_name' => fake()->optional()->name(),
            'third_party_phone' => fake()->optional()->phoneNumber(),
            'third_party_insurance' => fake()->optional()->company(),
            'estimated_cost' => fake()->randomFloat(2, 500, 15000),
            'final_cost' => null,
            'insurance_claim_number' => null,
            'insurance_status' => null,
            'expertised_date' => null,
            'repaired_date' => null,
            'closed_date' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the accident is severe.
     */
    public function severe(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'severe',
            'estimated_cost' => fake()->randomFloat(2, 8000, 25000),
            'police_report' => true,
            'police_report_number' => 'PV-' . strtoupper(fake()->bothify('#####-????')),
        ]);
    }

    /**
     * Indicate that the accident has been closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'expertised_date' => now()->subDays(30),
            'repaired_date' => now()->subDays(5),
            'closed_date' => now(),
            'final_cost' => $attributes['estimated_cost'] * fake()->randomFloat(2, 0.9, 1.3),
        ]);
    }

    /**
     * Indicate that the accident has insurance claim.
     */
    public function withInsuranceClaim(): static
    {
        return $this->state(fn (array $attributes) => [
            'insurance_claim_number' => 'CLAIM-' . strtoupper(fake()->bothify('####-????-###')),
            'insurance_status' => fake()->randomElement(['pending', 'accepted', 'rejected', 'partially_accepted']),
        ]);
    }
}
