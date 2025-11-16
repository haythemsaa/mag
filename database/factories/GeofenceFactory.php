<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Geofence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Geofence>
 */
class GeofenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['authorized', 'forbidden', 'client_site', 'depot', 'parking', 'service_area', 'delivery_zone', 'restricted'];
        $shapes = ['circle', 'polygon'];
        $shape = fake()->randomElement($shapes);

        $baseData = [
            'organization_id' => Organization::factory(),
            'name' => fake()->company() . ' - ' . fake()->randomElement(['Depot', 'Parking', 'Client Site', 'Service Area']),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement($types),
            'shape' => $shape,
            'alert_on_entry' => fake()->boolean(60),
            'alert_on_exit' => fake()->boolean(40),
            'is_active' => fake()->boolean(80),
            'active_from_time' => fake()->optional()->time('H:i:s'),
            'active_to_time' => fake()->optional()->time('H:i:s'),
            'active_days' => fake()->optional()->randomElements([1, 2, 3, 4, 5, 6, 7], fake()->numberBetween(1, 7)),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'France',
            'color' => fake()->hexColor(),
        ];

        // Add shape-specific data
        if ($shape === 'circle') {
            $baseData['center_latitude'] = fake()->latitude(41, 51); // France
            $baseData['center_longitude'] = fake()->longitude(-5, 10);
            $baseData['radius_meters'] = fake()->randomElement([100, 250, 500, 1000, 2000, 5000]);
            $baseData['polygon_coordinates'] = null;
        } else {
            // Generate a simple rectangular polygon
            $centerLat = fake()->latitude(41, 51);
            $centerLng = fake()->longitude(-5, 10);
            $offset = 0.005; // ~500m

            $baseData['center_latitude'] = null;
            $baseData['center_longitude'] = null;
            $baseData['radius_meters'] = null;
            $baseData['polygon_coordinates'] = [
                ['lat' => $centerLat + $offset, 'lng' => $centerLng - $offset],
                ['lat' => $centerLat + $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng - $offset],
            ];
        }

        return $baseData;
    }

    /**
     * Indicate that the geofence is a circle.
     */
    public function circle(): static
    {
        return $this->state(fn (array $attributes) => [
            'shape' => 'circle',
            'center_latitude' => fake()->latitude(41, 51),
            'center_longitude' => fake()->longitude(-5, 10),
            'radius_meters' => fake()->randomElement([100, 250, 500, 1000, 2000, 5000]),
            'polygon_coordinates' => null,
        ]);
    }

    /**
     * Indicate that the geofence is a polygon.
     */
    public function polygon(): static
    {
        $centerLat = fake()->latitude(41, 51);
        $centerLng = fake()->longitude(-5, 10);
        $offset = 0.005;

        return $this->state(fn (array $attributes) => [
            'shape' => 'polygon',
            'center_latitude' => null,
            'center_longitude' => null,
            'radius_meters' => null,
            'polygon_coordinates' => [
                ['lat' => $centerLat + $offset, 'lng' => $centerLng - $offset],
                ['lat' => $centerLat + $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng + $offset],
                ['lat' => $centerLat - $offset, 'lng' => $centerLng - $offset],
            ],
        ]);
    }

    /**
     * Indicate that the geofence is a depot type.
     */
    public function depot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'depot',
            'name' => fake()->company() . ' Depot',
        ]);
    }
}
