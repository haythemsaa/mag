<?php

namespace Database\Seeders;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChargingSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Please run OrganizationSeeder first.');
            return;
        }

        foreach ($organizations as $organization) {
            // Get electric vehicles for this organization
            $electricVehicles = Vehicle::where('organization_id', $organization->id)
                ->where('is_electric', true)
                ->get();

            if ($electricVehicles->isEmpty()) {
                $this->command->info("No electric vehicles found for organization {$organization->name}. Skipping...");
                continue;
            }

            // Get charging stations for this organization
            $chargingStations = ChargingStation::where('organization_id', $organization->id)->get();

            if ($chargingStations->isEmpty()) {
                $this->command->info("No charging stations found for organization {$organization->name}. Skipping...");
                continue;
            }

            // Get drivers (users) for this organization
            $drivers = User::where('organization_id', $organization->id)->get();

            // Create 10-20 completed charging sessions per vehicle
            foreach ($electricVehicles as $vehicle) {
                $completedCount = rand(10, 20);
                for ($i = 0; $i < $completedCount; $i++) {
                    ChargingSession::factory()
                        ->completed()
                        ->create([
                            'organization_id' => $organization->id,
                            'vehicle_id' => $vehicle->id,
                            'charging_station_id' => $chargingStations->random()->id,
                            'driver_id' => $drivers->isNotEmpty() ? $drivers->random()->id : null,
                        ]);
                }

                // Create 1-2 in-progress sessions per vehicle
                $inProgressCount = rand(1, 2);
                for ($i = 0; $i < $inProgressCount; $i++) {
                    ChargingSession::factory()
                        ->inProgress()
                        ->create([
                            'organization_id' => $organization->id,
                            'vehicle_id' => $vehicle->id,
                            'charging_station_id' => $chargingStations->random()->id,
                            'driver_id' => $drivers->isNotEmpty() ? $drivers->random()->id : null,
                        ]);
                }

                // Create 1-2 interrupted sessions per vehicle
                $interruptedCount = rand(1, 2);
                for ($i = 0; $i < $interruptedCount; $i++) {
                    ChargingSession::factory()
                        ->interrupted()
                        ->create([
                            'organization_id' => $organization->id,
                            'vehicle_id' => $vehicle->id,
                            'charging_station_id' => $chargingStations->random()->id,
                            'driver_id' => $drivers->isNotEmpty() ? $drivers->random()->id : null,
                        ]);
                }

                // Create 2-3 fast charging sessions per vehicle
                $fastChargingCount = rand(2, 3);
                for ($i = 0; $i < $fastChargingCount; $i++) {
                    // Find a fast charger station if available
                    $fastCharger = $chargingStations->where('max_power_kw', '>=', 50)->first();
                    $stationId = $fastCharger ? $fastCharger->id : $chargingStations->random()->id;

                    ChargingSession::factory()
                        ->completed()
                        ->fastCharging()
                        ->create([
                            'organization_id' => $organization->id,
                            'vehicle_id' => $vehicle->id,
                            'charging_station_id' => $stationId,
                            'driver_id' => $drivers->isNotEmpty() ? $drivers->random()->id : null,
                        ]);
                }
            }

            // Update charging stations with aggregated statistics
            foreach ($chargingStations as $station) {
                $sessions = ChargingSession::where('charging_station_id', $station->id)
                    ->where('status', 'completed')
                    ->get();

                if ($sessions->isNotEmpty()) {
                    $station->update([
                        'total_sessions' => $sessions->count(),
                        'total_energy_delivered_kwh' => $sessions->sum('energy_delivered_kwh'),
                        'total_revenue' => $sessions->sum('total_cost'),
                        'average_session_duration_minutes' => $sessions->avg('duration_minutes'),
                        'last_session_date' => $sessions->max('end_time'),
                    ]);
                }
            }
        }

        $this->command->info('Charging sessions seeded successfully.');
    }
}
