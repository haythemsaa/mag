<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Support\Collection;

class RouteOptimizationService
{
    /**
     * Optimize a route using the specified method
     */
    public function optimize(Route $route, string $method = 'nearest_neighbor'): Route
    {
        $stops = $route->stops()->get();

        if ($stops->count() < 2) {
            return $route; // No optimization needed for 0 or 1 stop
        }

        $optimizedStops = match ($method) {
            'nearest_neighbor' => $this->nearestNeighbor($stops),
            'two_opt' => $this->twoOpt($stops),
            default => $stops,
        };

        // Update stop numbers based on optimized order
        $this->updateStopOrder($optimizedStops);

        // Update distance and duration between stops
        $this->calculateDistancesAndDurations($optimizedStops);

        // Update route metrics
        $route->update([
            'is_optimized' => true,
            'optimization_method' => $method,
        ]);

        $route->updatePlannedMetrics();

        return $route->fresh();
    }

    /**
     * Nearest Neighbor algorithm
     * Simple greedy algorithm: always go to the nearest unvisited stop
     */
    protected function nearestNeighbor(Collection $stops): Collection
    {
        if ($stops->count() <= 1) {
            return $stops;
        }

        $optimized = collect();
        $remaining = $stops->values(); // Reset keys
        $current = $remaining->shift(); // Start with first stop
        $optimized->push($current);

        while ($remaining->isNotEmpty()) {
            $nearest = $this->findNearestStop($current, $remaining);
            $optimized->push($nearest);
            $remaining = $remaining->reject(fn($stop) => $stop->id === $nearest->id)->values();
            $current = $nearest;
        }

        return $optimized;
    }

    /**
     * 2-Opt algorithm
     * Iterative improvement algorithm that removes crossing edges
     */
    protected function twoOpt(Collection $stops): Collection
    {
        if ($stops->count() <= 3) {
            return $this->nearestNeighbor($stops); // Too few stops for 2-opt
        }

        // Start with nearest neighbor solution
        $route = $this->nearestNeighbor($stops)->values();
        $improved = true;
        $maxIterations = 100;
        $iteration = 0;

        while ($improved && $iteration < $maxIterations) {
            $improved = false;
            $iteration++;

            for ($i = 0; $i < $route->count() - 1; $i++) {
                for ($j = $i + 2; $j < $route->count(); $j++) {
                    // Skip if j is the last and i is first (can't improve)
                    if ($j === $route->count() - 1 && $i === 0) {
                        continue;
                    }

                    $currentDistance = $this->calculateDistance($route, $i, $j);
                    $newDistance = $this->calculateSwappedDistance($route, $i, $j);

                    if ($newDistance < $currentDistance) {
                        // Reverse the segment between i+1 and j
                        $segment = $route->slice($i + 1, $j - $i)->reverse();
                        $route = $route->slice(0, $i + 1)
                            ->concat($segment)
                            ->concat($route->slice($j + 1));
                        $route = $route->values();
                        $improved = true;
                    }
                }
            }
        }

        return $route;
    }

    /**
     * Find the nearest stop from current stop
     */
    protected function findNearestStop(RouteStop $current, Collection $stops): RouteStop
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stops as $stop) {
            $distance = $current->distanceFrom($stop);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $stop;
            }
        }

        return $nearest;
    }

    /**
     * Calculate total distance for current route segment
     */
    protected function calculateDistance(Collection $route, int $i, int $j): float
    {
        $d1 = $route[$i]->distanceFrom($route[$i + 1]);
        $d2 = $j < $route->count() - 1 ? $route[$j]->distanceFrom($route[$j + 1]) : 0;

        return $d1 + $d2;
    }

    /**
     * Calculate distance after swapping segment
     */
    protected function calculateSwappedDistance(Collection $route, int $i, int $j): float
    {
        $d1 = $route[$i]->distanceFrom($route[$j]);
        $d2 = $j < $route->count() - 1 ? $route[$i + 1]->distanceFrom($route[$j + 1]) : 0;

        return $d1 + $d2;
    }

    /**
     * Update stop numbers based on optimized order
     */
    protected function updateStopOrder(Collection $stops): void
    {
        $stops->each(function ($stop, $index) {
            $stop->update([
                'optimized_stop_number' => $index + 1,
            ]);
        });
    }

    /**
     * Calculate distances and durations between consecutive stops
     */
    protected function calculateDistancesAndDurations(Collection $stops): void
    {
        $previous = null;

        foreach ($stops as $index => $stop) {
            if ($previous === null) {
                // First stop - no previous
                $stop->update([
                    'distance_from_previous_km' => 0,
                    'duration_from_previous_minutes' => 0,
                ]);
            } else {
                $distance = $previous->distanceFrom($stop);
                $duration = $this->estimateDuration($distance);

                $stop->update([
                    'distance_from_previous_km' => $distance,
                    'duration_from_previous_minutes' => $duration,
                ]);
            }

            $previous = $stop;
        }
    }

    /**
     * Estimate duration based on distance
     * Assumes average speed of 50 km/h in urban areas
     */
    protected function estimateDuration(float $distanceKm, float $averageSpeedKmh = 50): int
    {
        return (int) ceil(($distanceKm / $averageSpeedKmh) * 60); // Convert to minutes
    }

    /**
     * Calculate estimated fuel cost for a route
     */
    public function estimateFuelCost(Route $route, float $fuelPricePerLiter = 1.80, float $consumptionPer100Km = 8.0): float
    {
        if (!$route->planned_distance_km) {
            return 0;
        }

        $fuelNeeded = ($route->planned_distance_km / 100) * $consumptionPer100Km;
        return $fuelNeeded * $fuelPricePerLiter;
    }

    /**
     * Calculate total estimated cost including fuel, driver time, vehicle wear
     */
    public function estimateTotalCost(
        Route $route,
        float $fuelPricePerLiter = 1.80,
        float $consumptionPer100Km = 8.0,
        float $driverHourlyRate = 15.00,
        float $vehicleWearPerKm = 0.15
    ): array {
        $fuelCost = $this->estimateFuelCost($route, $fuelPricePerLiter, $consumptionPer100Km);

        $driverCost = 0;
        if ($route->planned_duration_minutes) {
            $driverCost = ($route->planned_duration_minutes / 60) * $driverHourlyRate;
        }

        $wearCost = 0;
        if ($route->planned_distance_km) {
            $wearCost = $route->planned_distance_km * $vehicleWearPerKm;
        }

        $totalCost = $fuelCost + $driverCost + $wearCost;

        return [
            'fuel_cost' => round($fuelCost, 2),
            'driver_cost' => round($driverCost, 2),
            'wear_cost' => round($wearCost, 2),
            'total_cost' => round($totalCost, 2),
        ];
    }

    /**
     * Calculate savings from optimization
     */
    public function calculateOptimizationSavings(Route $route): array
    {
        // This requires storing original (unoptimized) metrics
        // For now, we estimate based on typical optimization improvements

        if (!$route->is_optimized || !$route->planned_distance_km) {
            return [
                'distance_saved_km' => 0,
                'time_saved_minutes' => 0,
                'cost_saved' => 0,
                'improvement_percent' => 0,
            ];
        }

        // Typical optimization savings: 10-25% for nearest neighbor, 15-30% for 2-opt
        $improvementPercent = match ($route->optimization_method) {
            'two_opt' => 20,  // 20% average improvement
            'nearest_neighbor' => 15, // 15% average improvement
            default => 0,
        };

        $originalDistance = $route->planned_distance_km / (1 - ($improvementPercent / 100));
        $distanceSaved = $originalDistance - $route->planned_distance_km;

        $originalDuration = $route->planned_duration_minutes / (1 - ($improvementPercent / 100));
        $timeSaved = $originalDuration - $route->planned_duration_minutes;

        $originalCost = $route->estimated_total_cost / (1 - ($improvementPercent / 100));
        $costSaved = $originalCost - $route->estimated_total_cost;

        return [
            'distance_saved_km' => round($distanceSaved, 2),
            'time_saved_minutes' => round($timeSaved),
            'cost_saved' => round($costSaved, 2),
            'improvement_percent' => $improvementPercent,
        ];
    }

    /**
     * Validate route for optimization
     */
    public function canOptimize(Route $route): array
    {
        $errors = [];

        if ($route->stops()->count() < 2) {
            $errors[] = 'Route must have at least 2 stops to optimize';
        }

        if ($route->isInProgress() || $route->isCompleted()) {
            $errors[] = 'Cannot optimize a route that is in progress or completed';
        }

        // Check if all stops have valid coordinates
        $invalidStops = $route->stops()->where(function ($query) {
            $query->whereNull('latitude')->orWhereNull('longitude');
        })->count();

        if ($invalidStops > 0) {
            $errors[] = "{$invalidStops} stop(s) have invalid coordinates";
        }

        return [
            'can_optimize' => empty($errors),
            'errors' => $errors,
        ];
    }
}
