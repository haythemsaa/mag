<?php

namespace Database\Seeders;

use App\Models\GpsPosition;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class GpsPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = Vehicle::with('driver')->get();

        // France major cities coordinates for realistic tracking
        $cities = [
            ['name' => 'Paris', 'lat' => 48.8566, 'lon' => 2.3522],
            ['name' => 'Lyon', 'lat' => 45.7640, 'lon' => 4.8357],
            ['name' => 'Marseille', 'lat' => 43.2965, 'lon' => 5.3698],
            ['name' => 'Toulouse', 'lat' => 43.6047, 'lon' => 1.4442],
            ['name' => 'Bordeaux', 'lat' => 44.8378, 'lon' => -0.5792],
            ['name' => 'Lille', 'lat' => 50.6292, 'lon' => 3.0573],
            ['name' => 'Nantes', 'lat' => 47.2184, 'lon' => -1.5536],
            ['name' => 'Strasbourg', 'lat' => 48.5734, 'lon' => 7.7521],
        ];

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $engineStatuses = ['running', 'idle', 'off'];

        $this->command->info('Seeding GPS positions...');

        foreach ($vehicles as $vehicle) {
            // Each vehicle gets 10-30 GPS positions over the past 7 days
            $numPositions = rand(10, 30);

            // Start from a random city
            $currentCity = $cities[array_rand($cities)];
            $currentLat = $currentCity['lat'] + (rand(-100, 100) / 1000); // Small variation
            $currentLon = $currentCity['lon'] + (rand(-100, 100) / 1000);

            // Initialize vehicle state
            $currentOdometer = max(0, $vehicle->mileage - rand(100, 500)); // Start from slightly lower mileage
            $currentFuelLevel = rand(30, 100); // Start with 30-100% fuel
            $currentBatteryLevel = rand(80, 100);
            $engineOn = true;

            for ($i = 0; $i < $numPositions; $i++) {
                // Time: positions spread over the past 7 days, with more recent positions
                $hoursAgo = rand(0, 7 * 24);
                $recordedAt = now()->subHours($hoursAgo);

                // Simulate vehicle movement (small increments in lat/lon)
                if ($engineOn && rand(0, 10) > 2) {
                    // 80% chance to move if engine is on
                    $latChange = (rand(-50, 50) / 10000); // ~5km max change
                    $lonChange = (rand(-50, 50) / 10000);
                    $currentLat += $latChange;
                    $currentLon += $lonChange;

                    // Update odometer (add 1-20 km)
                    $kmTraveled = rand(1, 20);
                    $currentOdometer += $kmTraveled;

                    // Fuel consumption (0.5-2% per position when moving)
                    $currentFuelLevel = max(10, $currentFuelLevel - rand(5, 20) / 10);

                    // Speed when moving (10-130 km/h)
                    $speed = rand(10, 130);
                } else {
                    // Stationary
                    $speed = 0;
                }

                // Battery level (slight decrease over time if engine off)
                if (!$engineOn) {
                    $currentBatteryLevel = max(50, $currentBatteryLevel - rand(0, 2));
                }

                // Randomly toggle engine status (10% chance)
                if (rand(0, 10) < 1) {
                    $engineOn = !$engineOn;
                }

                // If fuel is low, refuel
                if ($currentFuelLevel < 15) {
                    $currentFuelLevel = rand(80, 100);
                }

                // Engine status based on speed and engine state
                if (!$engineOn) {
                    $engineStatus = 'off';
                } elseif ($speed > 5) {
                    $engineStatus = 'running';
                } else {
                    $engineStatus = 'idle';
                }

                // Heading (0-360 degrees)
                $heading = rand(0, 360);

                // Direction based on heading
                $directionIndex = (int) round($heading / 45) % 8;
                $direction = $directions[$directionIndex];

                // Altitude (0-500m for France)
                $altitude = rand(0, 500);

                // GPS accuracy (2-15 meters)
                $accuracy = rand(2, 15);

                // Reverse geocode to get approximate address
                $address = $this->generateAddress($currentCity['name']);

                GpsPosition::create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $vehicle->current_driver_id,
                    'latitude' => round($currentLat, 6),
                    'longitude' => round($currentLon, 6),
                    'altitude' => $altitude,
                    'accuracy' => $accuracy,
                    'speed' => $speed,
                    'heading' => $heading,
                    'direction' => $direction,
                    'engine_on' => $engineOn,
                    'engine_status' => $engineStatus,
                    'fuel_level' => (int) $currentFuelLevel,
                    'battery_level' => (int) $currentBatteryLevel,
                    'odometer' => $currentOdometer,
                    'address' => $address['street'],
                    'city' => $address['city'],
                    'country' => 'FR',
                    'recorded_at' => $recordedAt,
                ]);

                // Occasionally change city (simulate long trips)
                if (rand(0, 100) < 5) {
                    $currentCity = $cities[array_rand($cities)];
                    $currentLat = $currentCity['lat'] + (rand(-100, 100) / 1000);
                    $currentLon = $currentCity['lon'] + (rand(-100, 100) / 1000);
                }
            }
        }

        $totalPositions = GpsPosition::count();
        $this->command->info("Created {$totalPositions} GPS positions");
        $this->command->info('  - Positions with engine on: ' . GpsPosition::where('engine_on', true)->count());
        $this->command->info('  - Positions with engine off: ' . GpsPosition::where('engine_on', false)->count());
        $this->command->info('  - Moving vehicles (speed > 5): ' .
            GpsPosition::where('speed', '>', 5)->distinct('vehicle_id')->count('vehicle_id'));
        $this->command->info('  - Average speed: ' . number_format(GpsPosition::where('speed', '>', 0)->avg('speed'), 1) . ' km/h');
        $this->command->info('  - Positions in last 24h: ' .
            GpsPosition::where('recorded_at', '>=', now()->subHours(24))->count());
    }

    /**
     * Generate realistic French address
     */
    private function generateAddress(string $cityName): array
    {
        $streetTypes = ['Rue', 'Avenue', 'Boulevard', 'Place', 'Allée', 'Impasse', 'Quai'];
        $streetNames = [
            'de la République', 'Victor Hugo', 'Jean Jaurès', 'du Général de Gaulle',
            'de la Liberté', 'des Champs-Élysées', 'Nationale', 'du Commerce',
            'de la Paix', 'de Verdun', 'Saint-Michel', 'Gambetta'
        ];

        $streetType = $streetTypes[array_rand($streetTypes)];
        $streetName = $streetNames[array_rand($streetNames)];
        $number = rand(1, 250);

        return [
            'street' => "{$number} {$streetType} {$streetName}",
            'city' => $cityName,
        ];
    }
}
