<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
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

        $this->command->info('Seeding routes and stops...');

        foreach ($organizations as $organization) {
            $this->seedRoutesForOrganization($organization);
        }

        $this->command->info('Routes and stops seeded successfully!');
    }

    /**
     * Seed routes for a specific organization
     */
    protected function seedRoutesForOrganization(Organization $organization): void
    {
        $vehicles = Vehicle::where('organization_id', $organization->id)->get();
        $drivers = Driver::where('organization_id', $organization->id)->get();

        if ($vehicles->isEmpty() || $drivers->isEmpty()) {
            $this->command->warn("Skipping organization {$organization->name} - no vehicles or drivers");
            return;
        }

        // 1. Draft Routes (being planned)
        $this->createDraftRoutes($organization, $vehicles, $drivers, 2);

        // 2. Planned Routes (ready to execute)
        $this->createPlannedRoutes($organization, $vehicles, $drivers, 3);

        // 3. In Progress Routes (currently executing)
        $this->createInProgressRoutes($organization, $vehicles, $drivers, 2);

        // 4. Completed Routes (historical data)
        $this->createCompletedRoutes($organization, $vehicles, $drivers, 10);

        // 5. Cancelled Routes
        $this->createCancelledRoutes($organization, $vehicles, $drivers, 1);

        // 6. Optimized Routes (showcase optimization)
        $this->createOptimizedRoutes($organization, $vehicles, $drivers, 2);
    }

    /**
     * Create draft routes (no stops yet or minimal stops)
     */
    protected function createDraftRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $route = Route::factory()
                ->forOrganization($organization)
                ->draft()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now()->addDays(fake()->numberBetween(3, 10)),
                ]);

            // Some draft routes have a few stops planned
            if (fake()->boolean(60)) {
                $this->createStopsForRoute($route, fake()->numberBetween(2, 5), 'pending');
            }
        }
    }

    /**
     * Create planned routes (ready with all stops)
     */
    protected function createPlannedRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $route = Route::factory()
                ->forOrganization($organization)
                ->planned()
                ->withAssignments()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now()->addDays(fake()->numberBetween(1, 7)),
                ]);

            // All planned routes have full stop list
            $stopCount = fake()->numberBetween(5, 15);
            $this->createStopsForRoute($route, $stopCount, 'pending');

            // Update route metrics
            $route->updatePlannedMetrics();
        }
    }

    /**
     * Create in-progress routes (currently executing)
     */
    protected function createInProgressRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $route = Route::factory()
                ->forOrganization($organization)
                ->inProgress()
                ->withAssignments()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now(),
                ]);

            $stopCount = fake()->numberBetween(8, 12);
            $completedCount = fake()->numberBetween(2, $stopCount - 2);

            // Create completed stops
            for ($j = 1; $j <= $completedCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($j)
                    ->completed()
                    ->create();
            }

            // Create one in-progress stop
            RouteStop::factory()
                ->forRoute($route)
                ->withStopNumber($completedCount + 1)
                ->inProgress()
                ->create();

            // Create remaining pending stops
            for ($j = $completedCount + 2; $j <= $stopCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($j)
                    ->pending()
                    ->create();
            }

            // Update route metrics
            $route->updatePlannedMetrics();
            $route->update(['completed_stops_count' => $completedCount]);
        }
    }

    /**
     * Create completed routes (historical)
     */
    protected function createCompletedRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $route = Route::factory()
                ->forOrganization($organization)
                ->completed()
                ->withAssignments()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now()->subDays(fake()->numberBetween(1, 60)),
                ]);

            $stopCount = fake()->numberBetween(6, 20);
            $completedCount = fake()->numberBetween((int)($stopCount * 0.7), $stopCount);
            $skippedCount = fake()->numberBetween(0, $stopCount - $completedCount);
            $failedCount = $stopCount - $completedCount - $skippedCount;

            $stopNumber = 1;

            // Create completed stops
            for ($j = 0; $j < $completedCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($stopNumber++)
                    ->completed()
                    ->create();
            }

            // Create skipped stops
            for ($j = 0; $j < $skippedCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($stopNumber++)
                    ->skipped()
                    ->create();
            }

            // Create failed stops
            for ($j = 0; $j < $failedCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($stopNumber++)
                    ->failed()
                    ->create();
            }

            // Update route metrics
            $route->updatePlannedMetrics();
            $route->update(['completed_stops_count' => $completedCount]);
        }
    }

    /**
     * Create cancelled routes
     */
    protected function createCancelledRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $route = Route::factory()
                ->forOrganization($organization)
                ->cancelled()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now()->subDays(fake()->numberBetween(1, 30)),
                ]);

            // Cancelled routes may have stops planned
            if (fake()->boolean(70)) {
                $this->createStopsForRoute($route, fake()->numberBetween(3, 10), 'pending');
                $route->updatePlannedMetrics();
            }
        }
    }

    /**
     * Create optimized routes (showcase optimization features)
     */
    protected function createOptimizedRoutes(Organization $organization, $vehicles, $drivers, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $method = fake()->randomElement(['nearest_neighbor', 'two_opt']);
            $state = $method === 'nearest_neighbor' ? 'optimizedNearestNeighbor' : 'optimizedTwoOpt';

            $route = Route::factory()
                ->forOrganization($organization)
                ->planned()
                ->withAssignments()
                ->$state()
                ->create([
                    'vehicle_id' => $vehicles->random()->id,
                    'driver_id' => $drivers->random()->id,
                    'planned_date' => now()->addDays(fake()->numberBetween(1, 5)),
                ]);

            // Create stops with optimized numbers
            $stopCount = fake()->numberBetween(10, 20);
            for ($j = 1; $j <= $stopCount; $j++) {
                RouteStop::factory()
                    ->forRoute($route)
                    ->withStopNumber($j)
                    ->pending()
                    ->create([
                        'optimized_stop_number' => $j, // Already optimized
                    ]);
            }

            // Update route metrics
            $route->updatePlannedMetrics();

            // Calculate estimated costs
            $fuelCost = ($route->planned_distance_km / 100) * 8.0 * 1.80; // 8L/100km, 1.80€/L
            $driverCost = ($route->planned_duration_minutes / 60) * 15.00; // 15€/h
            $wearCost = $route->planned_distance_km * 0.15; // 0.15€/km

            $route->update([
                'estimated_fuel_cost' => round($fuelCost, 2),
                'estimated_total_cost' => round($fuelCost + $driverCost + $wearCost, 2),
            ]);
        }
    }

    /**
     * Create stops for a route
     */
    protected function createStopsForRoute(Route $route, int $count, string $status): void
    {
        $types = ['pickup', 'delivery', 'service', 'visit'];

        for ($i = 1; $i <= $count; $i++) {
            $type = fake()->randomElement($types);

            $stop = RouteStop::factory()
                ->forRoute($route)
                ->withStopNumber($i)
                ->$type()
                ->create([
                    'status' => $status,
                ]);

            // Some stops have time windows
            if (fake()->boolean(50)) {
                $startHour = fake()->numberBetween(8, 16);
                $endHour = $startHour + fake()->numberBetween(2, 4);

                $stop->update([
                    'time_window_start' => sprintf('%02d:00', $startHour),
                    'time_window_end' => sprintf('%02d:00', min($endHour, 18)),
                ]);
            }

            // Some stops have packages
            if ($type === 'pickup' || $type === 'delivery') {
                if (fake()->boolean(80)) {
                    $stop->update([
                        'reference_number' => fake()->bothify('REF-####-????'),
                        'package_count' => fake()->numberBetween(1, 20),
                        'package_weight_kg' => fake()->randomFloat(2, 1, 500),
                        'package_volume_m3' => fake()->randomFloat(3, 0.1, 5),
                    ]);
                }
            }
        }
    }
}
