<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\Organization;
use App\Services\DriverScoringService;
use Illuminate\Console\Command;

class CalculateDriverScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scores:calculate-drivers
                            {--driver= : Calculate score for a specific driver ID}
                            {--organization= : Calculate scores for all drivers in an organization}
                            {--year= : Year for calculation (default: current)}
                            {--month= : Month for calculation (default: current)}
                            {--all : Calculate for all organizations}
                            {--recalculate : Recalculate existing scores}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate driver scores for a specific period';

    /**
     * Execute the console command.
     */
    public function handle(DriverScoringService $scoringService): int
    {
        $year = $this->option('year') ?? now()->year;
        $month = $this->option('month') ?? now()->month;

        // Validate month
        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12');

            return Command::FAILURE;
        }

        $this->info("Calculating driver scores for {$year}-{$month}");

        try {
            // Calculate for specific driver
            if ($driverId = $this->option('driver')) {
                return $this->calculateForDriver($scoringService, $driverId, $year, $month);
            }

            // Calculate for specific organization
            if ($organizationId = $this->option('organization')) {
                return $this->calculateForOrganization($scoringService, $organizationId, $year, $month);
            }

            // Calculate for all organizations
            if ($this->option('all')) {
                return $this->calculateForAllOrganizations($scoringService, $year, $month);
            }

            // No option specified
            $this->error('Please specify --driver, --organization, or --all');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error calculating scores: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Calculate score for a specific driver
     */
    protected function calculateForDriver(
        DriverScoringService $scoringService,
        int $driverId,
        int $year,
        int $month
    ): int {
        $driver = Driver::find($driverId);

        if (! $driver) {
            $this->error("Driver not found: {$driverId}");

            return Command::FAILURE;
        }

        $this->info("Calculating score for driver: {$driver->name} (ID: {$driver->id})");

        $score = $scoringService->calculateScore($driver, $year, $month);

        $this->info("Score calculated successfully!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Score', round($score->total_score, 2)],
                ['Safety', round($score->safety_score, 2)],
                ['Efficiency', round($score->efficiency_score, 2)],
                ['Compliance', round($score->compliance_score, 2)],
                ['Behavior', round($score->behavior_score, 2)],
                ['Grade', $score->getGrade()],
                ['Trend', $score->trend ?? 'N/A'],
                ['Rank', $score->organization_rank ?? 'N/A'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Calculate scores for all drivers in an organization
     */
    protected function calculateForOrganization(
        DriverScoringService $scoringService,
        int $organizationId,
        int $year,
        int $month
    ): int {
        $organization = Organization::find($organizationId);

        if (! $organization) {
            $this->error("Organization not found: {$organizationId}");

            return Command::FAILURE;
        }

        $this->info("Calculating scores for organization: {$organization->name}");

        $drivers = Driver::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        if ($drivers->isEmpty()) {
            $this->warn('No active drivers found in this organization');

            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($drivers->count());
        $progressBar->start();

        $calculated = 0;
        $errors = 0;

        foreach ($drivers as $driver) {
            try {
                $scoringService->calculateScore($driver, $year, $month);
                $calculated++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error calculating score for driver {$driver->id}: ".$e->getMessage());
                $errors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Calculation complete!");
        $this->info("Successfully calculated: {$calculated}");

        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors}");
        }

        return Command::SUCCESS;
    }

    /**
     * Calculate scores for all organizations
     */
    protected function calculateForAllOrganizations(
        DriverScoringService $scoringService,
        int $year,
        int $month
    ): int {
        $this->info('Calculating scores for all organizations');

        $organizations = Organization::where('is_active', true)->get();

        if ($organizations->isEmpty()) {
            $this->warn('No active organizations found');

            return Command::SUCCESS;
        }

        $this->info("Found {$organizations->count()} active organizations");

        $totalCalculated = 0;
        $totalErrors = 0;

        foreach ($organizations as $organization) {
            $this->info("\nProcessing organization: {$organization->name} (ID: {$organization->id})");

            $drivers = Driver::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();

            if ($drivers->isEmpty()) {
                $this->warn('  No active drivers found');

                continue;
            }

            $progressBar = $this->output->createProgressBar($drivers->count());
            $progressBar->start();

            $calculated = 0;
            $errors = 0;

            foreach ($drivers as $driver) {
                try {
                    $scoringService->calculateScore($driver, $year, $month);
                    $calculated++;
                    $totalCalculated++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("  Error for driver {$driver->id}: ".$e->getMessage());
                    $errors++;
                    $totalErrors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
            $this->info("  Calculated: {$calculated}, Errors: {$errors}");
        }

        $this->newLine();
        $this->info('All organizations processed!');
        $this->info("Total scores calculated: {$totalCalculated}");

        if ($totalErrors > 0) {
            $this->warn("Total errors encountered: {$totalErrors}");
        }

        return Command::SUCCESS;
    }
}
