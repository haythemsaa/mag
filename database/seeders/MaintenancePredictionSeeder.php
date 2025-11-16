<?php

namespace Database\Seeders;

use App\Models\MaintenancePrediction;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class MaintenancePredictionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all organizations
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Please run OrganizationSeeder first.');

            return;
        }

        foreach ($organizations as $organization) {
            $this->command->info("Seeding predictions for: {$organization->name}");

            // Get vehicles for this organization
            $vehicles = Vehicle::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();

            if ($vehicles->isEmpty()) {
                $this->command->warn("  No active vehicles found for {$organization->name}");

                continue;
            }

            // Create variety of predictions for each vehicle
            foreach ($vehicles as $vehicle) {
                // 50% chance of having predictions
                if (fake()->boolean(50)) {
                    continue;
                }

                // 1-3 predictions per vehicle
                $predictionCount = fake()->numberBetween(1, 3);

                for ($i = 0; $i < $predictionCount; $i++) {
                    // Determine prediction type and characteristics
                    $predictionType = fake()->randomElement([
                        'maintenance_due',
                        'part_failure',
                        'cost_overrun',
                        'fuel_efficiency_drop',
                        'tire_replacement',
                        'oil_change',
                        'inspection_due',
                        'contract_expiry',
                    ]);

                    // Determine status distribution
                    $status = fake()->randomElement([
                        'pending' => 40, // 40%
                        'acknowledged' => 30, // 30%
                        'scheduled' => 15, // 15%
                        'completed' => 10, // 10%
                        'dismissed' => 5, // 5%
                    ]);

                    // Create based on type
                    $factory = MaintenancePrediction::factory()
                        ->for($vehicle)
                        ->for($organization);

                    // Apply type-specific factory
                    $factory = match ($predictionType) {
                        'maintenance_due' => $factory->maintenanceDue(),
                        'part_failure' => $factory->partFailure(),
                        'cost_overrun' => $factory->costOverrun(),
                        'fuel_efficiency_drop' => $factory->fuelEfficiencyDrop(),
                        'contract_expiry' => $factory->contractExpiry(),
                        default => $factory,
                    };

                    // Apply status
                    $factory = match ($status) {
                        'pending' => $factory->pending(),
                        'acknowledged' => $factory->acknowledged(),
                        'scheduled' => $factory->scheduled(),
                        'completed' => $factory->completed(),
                        'dismissed' => $factory->dismissed(),
                        default => $factory,
                    };

                    $factory->create();
                }
            }

            // Create some special case predictions
            $this->createSpecialCases($organization, $vehicles);

            $this->command->info("  ✓ Predictions created for {$organization->name}");
        }

        $this->command->info('Maintenance predictions seeded successfully!');
    }

    /**
     * Create special case predictions for testing
     */
    protected function createSpecialCases(Organization $organization, $vehicles): void
    {
        if ($vehicles->isEmpty()) {
            return;
        }

        // 1. Critical overdue prediction
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->critical()
            ->overdue()
            ->pending()
            ->partFailure()
            ->create();

        // 2. High confidence prediction due soon
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->high()
            ->dueSoon()
            ->highConfidence()
            ->pending()
            ->maintenanceDue()
            ->create();

        // 3. Acknowledged prediction with high priority
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->high()
            ->highConfidence()
            ->acknowledged()
            ->create();

        // 4. Multiple predictions for one vehicle (pattern)
        $patternVehicle = $vehicles->random();
        for ($i = 0; $i < 3; $i++) {
            MaintenancePrediction::factory()
                ->for($patternVehicle)
                ->for($organization)
                ->fuelEfficiencyDrop()
                ->pending()
                ->create();
        }

        // 5. Cost overrun with high estimated cost
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->costOverrun()
            ->critical()
            ->state([
                'estimated_cost_min' => 2000,
                'estimated_cost_max' => 5000,
                'estimated_cost_avg' => 3500,
            ])
            ->create();

        // 6. Scheduled maintenance prediction
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->scheduled()
            ->highConfidence()
            ->maintenanceDue()
            ->create();

        // 7. Dismissed low confidence prediction
        MaintenancePrediction::factory()
            ->for($vehicles->random())
            ->for($organization)
            ->dismissed()
            ->lowConfidence()
            ->state([
                'dismissal_reason' => 'False positive - maintenance already completed',
            ])
            ->create();
    }
}
