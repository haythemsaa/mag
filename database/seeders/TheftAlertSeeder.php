<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\TheftAlert;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class TheftAlertSeeder extends Seeder
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
            // Get vehicles for this organization
            $vehicles = Vehicle::where('organization_id', $organization->id)->get();

            if ($vehicles->isEmpty()) {
                $this->command->info("No vehicles found for organization {$organization->name}. Skipping...");
                continue;
            }

            // Get users (for assignment)
            $users = User::where('organization_id', $organization->id)->get();

            // Create 3-5 pending alerts (recent, unresolved)
            $pendingCount = rand(3, 5);
            for ($i = 0; $i < $pendingCount; $i++) {
                TheftAlert::factory()
                    ->pending()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                    ]);
            }

            // Create 2-3 alerts under investigation
            $investigatingCount = rand(2, 3);
            for ($i = 0; $i < $investigatingCount; $i++) {
                TheftAlert::factory()
                    ->investigating()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Create 15-20 false alarms (historical data)
            $falseAlarmCount = rand(15, 20);
            for ($i = 0; $i < $falseAlarmCount; $i++) {
                TheftAlert::factory()
                    ->falseAlarm()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Create 1-2 confirmed thefts (rare but important)
            $confirmedTheftCount = rand(1, 2);
            for ($i = 0; $i < $confirmedTheftCount; $i++) {
                TheftAlert::factory()
                    ->confirmedTheft()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Create 5-8 resolved alerts
            $resolvedCount = rand(5, 8);
            for ($i = 0; $i < $resolvedCount; $i++) {
                TheftAlert::factory()
                    ->resolved()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Create some specific type alerts for variety

            // Movement outside hours (2-3)
            $outsideHoursCount = rand(2, 3);
            for ($i = 0; $i < $outsideHoursCount; $i++) {
                TheftAlert::factory()
                    ->movementOutsideHours()
                    ->falseAlarm()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Unauthorized ignition (1-2, critical)
            $unauthorizedIgnitionCount = rand(1, 2);
            for ($i = 0; $i < $unauthorizedIgnitionCount; $i++) {
                TheftAlert::factory()
                    ->unauthorizedIgnition()
                    ->notificationsSent()
                    ->create([
                        'organization_id' => $organization->id,
                        'vehicle_id' => $vehicles->random()->id,
                        'status' => $this->faker->randomElement(['investigating', 'resolved']),
                        'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    ]);
            }

            // Towing detected (1, critical)
            TheftAlert::factory()
                ->towingDetected()
                ->notificationsSent()
                ->create([
                    'organization_id' => $organization->id,
                    'vehicle_id' => $vehicles->random()->id,
                    'status' => 'investigating',
                    'assigned_to_user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    'investigation_notes' => 'Attempting to contact driver. Vehicle shows movement without ignition.',
                ]);

            $this->command->info("Created theft alerts for organization: {$organization->name}");
        }

        $this->command->info('Theft alerts seeded successfully.');
    }
}
