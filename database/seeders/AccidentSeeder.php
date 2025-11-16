<?php

namespace Database\Seeders;

use App\Models\Accident;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Database\Seeder;

class AccidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            $vehicles = $organization->vehicles()->take(10)->get();
            $drivers = $organization->drivers()->take(10)->get();

            if ($vehicles->isEmpty() || $drivers->isEmpty()) {
                continue;
            }

            // Create 8-15 accidents per organization
            $accidentCount = rand(8, 15);

            for ($i = 0; $i < $accidentCount; $i++) {
                $vehicle = $vehicles->random();
                $driver = $drivers->random();

                // 40% closed, 30% repaired, 20% in_progress, 10% declared
                $statusRand = rand(1, 100);
                if ($statusRand <= 40) {
                    Accident::factory()->closed()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                    ]);
                } elseif ($statusRand <= 70) {
                    Accident::factory()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'status' => 'repaired',
                        'repaired_date' => now()->subDays(rand(1, 15)),
                    ]);
                } elseif ($statusRand <= 90) {
                    Accident::factory()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'status' => 'in_progress',
                    ]);
                } else {
                    Accident::factory()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'status' => 'declared',
                    ]);
                }
            }

            // Create a few severe accidents with insurance claims
            $severeCount = rand(2, 5);
            for ($i = 0; $i < $severeCount; $i++) {
                $vehicle = $vehicles->random();
                $driver = $drivers->random();

                Accident::factory()->severe()->withInsuranceClaim()->create([
                    'organization_id' => $organization->id,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                ]);
            }
        }
    }
}
