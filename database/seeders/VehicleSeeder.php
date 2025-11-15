<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\Organization;
use App\Models\Site;
use App\Models\Driver;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        $vehicleTemplates = [
            ['brand' => 'Renault', 'model' => 'Kangoo', 'type' => 'Van', 'fuel_type' => 'diesel'],
            ['brand' => 'Peugeot', 'model' => 'Partner', 'type' => 'Van', 'fuel_type' => 'diesel'],
            ['brand' => 'Citroën', 'model' => 'Berlingo', 'type' => 'Van', 'fuel_type' => 'diesel'],
            ['brand' => 'Mercedes', 'model' => 'Sprinter', 'type' => 'Truck', 'fuel_type' => 'diesel'],
            ['brand' => 'Volkswagen', 'model' => 'Transporter', 'type' => 'Van', 'fuel_type' => 'diesel'],
            ['brand' => 'Renault', 'model' => 'Zoe', 'type' => 'Car', 'fuel_type' => 'electric'],
            ['brand' => 'Tesla', 'model' => 'Model 3', 'type' => 'Car', 'fuel_type' => 'electric'],
            ['brand' => 'Peugeot', 'model' => '3008', 'type' => 'SUV', 'fuel_type' => 'hybrid'],
        ];

        foreach ($organizations as $organization) {
            $sites = $organization->sites;
            $drivers = $organization->drivers;
            $vehicleCount = min(rand(15, 40), $organization->max_vehicles);

            for ($i = 0; $i < $vehicleCount; $i++) {
                $template = $vehicleTemplates[array_rand($vehicleTemplates)];
                $site = $sites->count() > 0 ? $sites->random() : null;
                $year = rand(2018, 2024);

                // Assign driver to some vehicles
                $currentDriver = ($i % 2 == 0 && $drivers->count() > 0) ? $drivers->random() : null;
                $status = $currentDriver ? 'assigned' : ['available', 'maintenance', 'available'][array_rand(['available', 'maintenance', 'available'])];

                $initialMileage = rand(5000, 50000);
                $currentMileage = $initialMileage + rand(1000, 20000);

                Vehicle::create([
                    'organization_id' => $organization->id,
                    'site_id' => $site?->id,
                    'current_driver_id' => $currentDriver?->id,
                    'vin' => strtoupper(substr($template['brand'], 0, 3)) . rand(1000000000000, 9999999999999),
                    'registration' => strtoupper(substr($template['brand'], 0, 2)) . '-' . rand(100, 999) . '-' . strtoupper(chr(rand(65, 90)) . chr(rand(65, 90))),
                    'brand' => $template['brand'],
                    'model' => $template['model'],
                    'type' => $template['type'],
                    'year' => $year,
                    'acquisition_mode' => ['purchase', 'lease', 'rental'][array_rand(['purchase', 'lease', 'rental'])],
                    'acquisition_date' => now()->subMonths(rand(6, 48)),
                    'acquisition_price' => rand(15000, 45000),
                    'fuel_type' => $template['fuel_type'],
                    'tank_capacity' => $template['fuel_type'] === 'electric' ? 50 : rand(50, 90),
                    'engine_capacity' => $template['fuel_type'] === 'electric' ? null : rand(1200, 2500),
                    'power_hp' => rand(75, 180),
                    'co2_emission' => $template['fuel_type'] === 'electric' ? 0 : rand(95, 180),
                    'pollution_standard' => ['Euro 5', 'Euro 6', 'Euro 6d'][array_rand(['Euro 5', 'Euro 6', 'Euro 6d'])],
                    'initial_mileage' => $initialMileage,
                    'current_mileage' => $currentMileage,
                    'last_mileage_update' => now()->subDays(rand(1, 30)),
                    'technical_control_date' => now()->addMonths(rand(3, 18)),
                    'insurance_expiry' => now()->addMonths(rand(6, 12)),
                    'next_service_mileage' => $currentMileage + rand(5000, 15000),
                    'color' => ['white', 'black', 'silver', 'blue', 'red'][array_rand(['white', 'black', 'silver', 'blue', 'red'])],
                    'seats' => $template['type'] === 'Truck' ? 3 : rand(2, 5),
                    'doors' => $template['type'] === 'Truck' ? 2 : rand(3, 5),
                    'payload_capacity' => $template['type'] === 'Truck' ? rand(1000, 3500) : rand(500, 1000),
                    'gps_enabled' => (bool)rand(0, 1),
                    'gps_provider' => (bool)rand(0, 1) ? 'TomTom Fleet' : null,
                    'status' => $status,
                    'notes' => $i % 10 == 0 ? 'Véhicule en excellent état' : null,
                ]);
            }
        }
    }
}
