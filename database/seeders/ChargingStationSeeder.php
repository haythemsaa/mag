<?php

namespace Database\Seeders;

use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\Site;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChargingStationSeeder extends Seeder
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
            // Get sites for this organization
            $sites = Site::where('organization_id', $organization->id)->get();

            // Create 2-3 depot charging stations
            $depotCount = rand(2, 3);
            for ($i = 0; $i < $depotCount; $i++) {
                ChargingStation::factory()
                    ->depot()
                    ->create([
                        'organization_id' => $organization->id,
                        'site_id' => $sites->isNotEmpty() ? $sites->random()->id : null,
                    ]);
            }

            // Create 1-2 public fast chargers
            $publicCount = rand(1, 2);
            for ($i = 0; $i < $publicCount; $i++) {
                ChargingStation::factory()
                    ->publicFastCharger()
                    ->create([
                        'organization_id' => $organization->id,
                        'site_id' => null, // Public stations are not tied to a specific site
                    ]);
            }

            // Create 1 station in maintenance (unavailable)
            ChargingStation::factory()
                ->unavailable()
                ->create([
                    'organization_id' => $organization->id,
                    'site_id' => $sites->isNotEmpty() ? $sites->random()->id : null,
                ]);
        }

        $this->command->info('Charging stations seeded successfully.');
    }
}
