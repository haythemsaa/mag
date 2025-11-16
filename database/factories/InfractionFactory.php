<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\Infraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infraction>
 */
class InfractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['speeding', 'red_light', 'parking', 'phone', 'seatbelt', 'alcohol', 'dangerous_driving', 'stop_sign', 'wrong_way', 'other'];
        $statuses = ['received', 'pending', 'assigned', 'contested', 'paid', 'cancelled'];

        $amount = fake()->randomFloat(2, 35, 750);
        $reducedAmount = $amount * 0.66; // Typically 2/3 of original amount

        return [
            'organization_id' => Organization::factory(),
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'infraction_number' => 'INF-' . now()->year . '-' . str_pad(fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'type' => fake()->randomElement($types),
            'infraction_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'location' => fake()->streetAddress() . ', ' . fake()->city(),
            'amount' => $amount,
            'reduced_amount' => $reducedAmount,
            'points_deducted' => fake()->randomElement([0, 1, 2, 3, 4, 6]),
            'speed_recorded' => fake()->optional()->numberBetween(55, 180),
            'speed_limit' => fake()->optional()->numberBetween(30, 130),
            'due_date' => now()->addDays(fake()->numberBetween(30, 90)),
            'reduced_due_date' => now()->addDays(fake()->numberBetween(10, 30)),
            'status' => fake()->randomElement($statuses),
            'payment_method' => null,
            'payment_reference' => null,
            'paid_date' => null,
            'contested_date' => null,
            'contest_notes' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the infraction has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_method' => fake()->randomElement(['bank_transfer', 'credit_card', 'cash', 'check']),
            'payment_reference' => 'PAY-' . strtoupper(fake()->bothify('###???###')),
            'paid_date' => now(),
        ]);
    }

    /**
     * Indicate that the infraction has been contested.
     */
    public function contested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'contested',
            'contested_date' => now(),
            'contest_notes' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the infraction is for speeding.
     */
    public function speeding(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'speeding',
            'speed_recorded' => fake()->numberBetween(65, 180),
            'speed_limit' => fake()->numberBetween(30, 130),
        ]);
    }
}
