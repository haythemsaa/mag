<?php

namespace Database\Seeders;

use App\Models\Infraction;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Database\Seeder;

class InfractionSeeder extends Seeder
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

            // Create 15-30 infractions per organization
            $infractionCount = rand(15, 30);

            for ($i = 0; $i < $infractionCount; $i++) {
                $vehicle = $vehicles->random();
                $driver = $drivers->random();

                // 70% paid, 20% pending, 10% contested
                $statusRand = rand(1, 100);
                if ($statusRand <= 70) {
                    Infraction::factory()->paid()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                    ]);
                } elseif ($statusRand <= 90) {
                    Infraction::factory()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'status' => 'pending',
                    ]);
                } else {
                    Infraction::factory()->contested()->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                    ]);
                }
            }
        }
    }
}
