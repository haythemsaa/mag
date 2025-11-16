<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DriverScoreSeeder extends Seeder
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
            $this->command->info("Seeding driver scores for: {$organization->name}");

            // Get active drivers for this organization
            $drivers = Driver::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();

            if ($drivers->isEmpty()) {
                $this->command->warn("  No active drivers found for {$organization->name}");

                continue;
            }

            // Seed scores for last 6 months
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $year = $date->year;
                $month = $date->month;

                $this->command->info("  Creating scores for {$year}-{$month}");

                foreach ($drivers as $index => $driver) {
                    // Determine score quality based on driver position (create variety)
                    $scoreType = match (true) {
                        $index % 5 === 0 => 'excellent', // 20% excellent
                        $index % 5 === 1 => 'good', // 20% good
                        $index % 5 === 2 => 'average', // 20% average
                        $index % 5 === 3 => 'good', // 20% good (more good than poor)
                        default => 'average', // 20% average
                    };

                    // Occasionally create poor performers
                    if ($index % 7 === 0) {
                        $scoreType = 'poor';
                    }

                    // Determine trend (most stable, some improving/declining)
                    $trendType = match (true) {
                        $i === 5 => null, // First month - no trend
                        $index % 4 === 0 => 'improving',
                        $index % 4 === 1 => 'declining',
                        default => 'stable',
                    };

                    // Create the score
                    $factory = DriverScore::factory()
                        ->for($driver)
                        ->for($organization)
                        ->forPeriod($year, $month)
                        ->$scoreType()
                        ->withFullMetrics();

                    // Apply trend if applicable
                    if ($trendType) {
                        $factory = $factory->$trendType();
                    }

                    $factory->create();
                }

                // After creating all scores for this period, update ranks
                $this->updateRanks($organization->id, $year, $month);
            }

            // Create some special cases for current month
            $currentYear = now()->year;
            $currentMonth = now()->month;

            // Top 3 performers
            if ($drivers->count() >= 3) {
                $topDrivers = $drivers->take(3);

                foreach ($topDrivers as $rank => $driver) {
                    // Delete existing score if any
                    DriverScore::where('driver_id', $driver->id)
                        ->where('year', $currentYear)
                        ->where('month', $currentMonth)
                        ->delete();

                    // Create top performer score
                    DriverScore::factory()
                        ->for($driver)
                        ->for($organization)
                        ->currentMonth()
                        ->topPerformer()
                        ->improving()
                        ->perfectSafety()
                        ->efficient()
                        ->smoothDriver()
                        ->compliant()
                        ->state([
                            'organization_rank' => $rank + 1,
                            'total_drivers_in_org' => $drivers->count(),
                        ])
                        ->create();
                }
            }

            // Bottom 2 performers
            if ($drivers->count() >= 5) {
                $bottomDrivers = $drivers->slice(-2);

                foreach ($bottomDrivers as $index => $driver) {
                    // Delete existing score if any
                    DriverScore::where('driver_id', $driver->id)
                        ->where('year', $currentYear)
                        ->where('month', $currentMonth)
                        ->delete();

                    // Create bottom performer score
                    DriverScore::factory()
                        ->for($driver)
                        ->for($organization)
                        ->currentMonth()
                        ->bottomPerformer()
                        ->declining()
                        ->state([
                            'organization_rank' => $drivers->count() - (1 - $index),
                            'total_drivers_in_org' => $drivers->count(),
                        ])
                        ->create();
                }
            }

            // Update ranks for current month again after adding special cases
            $this->updateRanks($organization->id, $currentYear, $currentMonth);
        }

        $this->command->info('Driver scores seeded successfully!');
    }

    /**
     * Update organization ranks for a specific period
     */
    protected function updateRanks(int $organizationId, int $year, int $month): void
    {
        $scores = DriverScore::where('organization_id', $organizationId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('total_score', 'desc')
            ->get();

        $totalDrivers = $scores->count();

        foreach ($scores as $index => $score) {
            $score->update([
                'organization_rank' => $index + 1,
                'total_drivers_in_org' => $totalDrivers,
            ]);
        }
    }
}
