<?php

namespace Database\Seeders;

use App\Models\Geofence;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class GeofenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            // Create 5-10 geofences per organization
            $geofenceCount = rand(5, 10);

            for ($i = 0; $i < $geofenceCount; $i++) {
                // 60% circles, 40% polygons
                if (rand(1, 100) <= 60) {
                    Geofence::factory()->circle()->create([
                        'organization_id' => $organization->id,
                    ]);
                } else {
                    Geofence::factory()->polygon()->create([
                        'organization_id' => $organization->id,
                    ]);
                }
            }

            // Create specific depot geofences
            Geofence::factory()->circle()->depot()->create([
                'organization_id' => $organization->id,
                'name' => $organization->name . ' - Main Depot',
                'alert_on_entry' => true,
                'alert_on_exit' => true,
                'is_active' => true,
            ]);

            // Create parking geofence
            Geofence::factory()->circle()->create([
                'organization_id' => $organization->id,
                'type' => 'parking',
                'name' => $organization->name . ' - Parking Area',
                'alert_on_entry' => false,
                'alert_on_exit' => false,
                'is_active' => true,
            ]);

            // Create a forbidden zone
            Geofence::factory()->polygon()->create([
                'organization_id' => $organization->id,
                'type' => 'forbidden',
                'name' => 'Restricted Area',
                'alert_on_entry' => true,
                'alert_on_exit' => false,
                'is_active' => true,
            ]);
        }
    }
}
