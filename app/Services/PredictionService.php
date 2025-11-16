<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Cost;
use App\Models\FuelTransaction;
use App\Models\Maintenance;
use App\Models\MaintenancePrediction;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PredictionService
{
    /**
     * Generate all predictions for a vehicle
     */
    public function generatePredictionsForVehicle(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Only generate predictions for active vehicles
        if (!$vehicle->is_active) {
            return $predictions;
        }

        // 1. Predict maintenance based on mileage and time
        $maintenancePredictions = $this->predictMaintenance($vehicle);
        $predictions = $predictions->merge($maintenancePredictions);

        // 2. Predict part failures based on historical patterns
        $partFailurePredictions = $this->predictPartFailures($vehicle);
        $predictions = $predictions->merge($partFailurePredictions);

        // 3. Predict cost overruns
        $costPredictions = $this->predictCostOverruns($vehicle);
        $predictions = $predictions->merge($costPredictions);

        // 4. Predict fuel efficiency drops
        $fuelPredictions = $this->predictFuelEfficiencyDrop($vehicle);
        $predictions = $predictions->merge($fuelPredictions);

        // 5. Predict tire replacement (if tracked)
        $tirePredictions = $this->predictTireReplacement($vehicle);
        $predictions = $predictions->merge($tirePredictions);

        // 6. Predict contract expiry
        $contractPredictions = $this->predictContractExpiry($vehicle);
        $predictions = $predictions->merge($contractPredictions);

        return $predictions;
    }

    /**
     * Predict maintenance based on mileage and time intervals
     */
    protected function predictMaintenance(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Get last maintenance
        $lastMaintenance = Maintenance::where('vehicle_id', $vehicle->id)
            ->whereIn('maintenance_type', ['preventive', 'inspection'])
            ->where('status', 'completed')
            ->latest('completion_date')
            ->first();

        if (!$lastMaintenance) {
            // No maintenance history, predict based on vehicle age
            $daysSincePurchase = $vehicle->purchase_date ? now()->diffInDays($vehicle->purchase_date) : 0;

            if ($daysSincePurchase > 300) { // ~10 months without maintenance
                $prediction = $this->createPrediction($vehicle, [
                    'prediction_type' => 'maintenance_due',
                    'title' => 'Preventive Maintenance Overdue',
                    'description' => 'No maintenance records found. Vehicle may require inspection and preventive maintenance.',
                    'confidence' => 'medium',
                    'priority' => 'high',
                    'predicted_date' => now()->addDays(7),
                    'algorithm_used' => 'time_based',
                    'estimated_cost_min' => 150,
                    'estimated_cost_max' => 500,
                    'estimated_cost_avg' => 300,
                    'recommended_actions' => [
                        'Schedule comprehensive vehicle inspection',
                        'Check all fluid levels',
                        'Inspect brake system',
                        'Check tire condition',
                    ],
                ]);

                $predictions->push($prediction);
            }

            return $predictions;
        }

        // Calculate average maintenance interval
        $maintenanceHistory = Maintenance::where('vehicle_id', $vehicle->id)
            ->where('status', 'completed')
            ->orderBy('completion_date')
            ->get();

        if ($maintenanceHistory->count() >= 2) {
            // Calculate average interval in days
            $intervals = [];
            for ($i = 1; $i < $maintenanceHistory->count(); $i++) {
                $prev = $maintenanceHistory[$i - 1];
                $current = $maintenanceHistory[$i];
                $intervals[] = $prev->completion_date->diffInDays($current->completion_date);
            }

            $avgInterval = count($intervals) > 0 ? array_sum($intervals) / count($intervals) : 180;
            $daysSinceLastMaintenance = now()->diffInDays($lastMaintenance->completion_date);

            // Predict next maintenance with 80% of average interval
            $predictedDaysUntil = ($avgInterval * 0.8) - $daysSinceLastMaintenance;

            if ($predictedDaysUntil <= 30) {
                $confidence = $predictedDaysUntil <= 7 ? 'high' : 'medium';
                $priority = $predictedDaysUntil <= 7 ? 'high' : 'medium';

                $prediction = $this->createPrediction($vehicle, [
                    'prediction_type' => 'maintenance_due',
                    'title' => 'Preventive Maintenance Due Soon',
                    'description' => "Based on historical maintenance pattern (avg interval: {$avgInterval} days), next maintenance is predicted.",
                    'confidence' => $confidence,
                    'priority' => $priority,
                    'predicted_date' => now()->addDays(max(0, $predictedDaysUntil)),
                    'algorithm_used' => 'pattern_matching',
                    'algorithm_params' => [
                        'avg_interval_days' => $avgInterval,
                        'days_since_last' => $daysSinceLastMaintenance,
                        'historical_count' => $maintenanceHistory->count(),
                    ],
                    'estimated_cost_min' => 200,
                    'estimated_cost_max' => 600,
                    'estimated_cost_avg' => 400,
                    'recommended_actions' => [
                        'Schedule preventive maintenance',
                        'Order common replacement parts',
                        'Review last maintenance report',
                    ],
                ]);

                $predictions->push($prediction);
            }
        }

        // Mileage-based prediction
        if ($vehicle->current_mileage_km) {
            $mileageSinceLastMaintenance = $lastMaintenance->odometer_reading
                ? $vehicle->current_mileage_km - $lastMaintenance->odometer_reading
                : 0;

            // Standard intervals: 15000 km for most vehicles
            $standardInterval = 15000;

            if ($mileageSinceLastMaintenance >= $standardInterval * 0.8) {
                $kmRemaining = $standardInterval - $mileageSinceLastMaintenance;
                $avgDailyKm = $this->calculateAverageDailyMileage($vehicle);
                $daysUntilDue = $avgDailyKm > 0 ? $kmRemaining / $avgDailyKm : 30;

                $prediction = $this->createPrediction($vehicle, [
                    'prediction_type' => 'maintenance_due',
                    'title' => 'Mileage-Based Maintenance Due',
                    'description' => "Vehicle approaching {$standardInterval}km maintenance interval.",
                    'confidence' => 'high',
                    'priority' => $daysUntilDue <= 14 ? 'high' : 'medium',
                    'predicted_date' => now()->addDays(max(0, $daysUntilDue)),
                    'current_odometer_km' => $vehicle->current_mileage_km,
                    'predicted_odometer_km' => $vehicle->current_mileage_km + $kmRemaining,
                    'algorithm_used' => 'mileage_based',
                    'algorithm_params' => [
                        'standard_interval_km' => $standardInterval,
                        'current_mileage' => $vehicle->current_mileage_km,
                        'km_since_last' => $mileageSinceLastMaintenance,
                        'avg_daily_km' => $avgDailyKm,
                    ],
                    'estimated_cost_min' => 200,
                    'estimated_cost_max' => 600,
                    'estimated_cost_avg' => 400,
                ]);

                $predictions->push($prediction);
            }
        }

        return $predictions;
    }

    /**
     * Predict part failures based on historical patterns
     */
    protected function predictPartFailures(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Analyze maintenance history for recurring issues
        $maintenanceHistory = Maintenance::where('vehicle_id', $vehicle->id)
            ->where('status', 'completed')
            ->orderBy('completion_date', 'desc')
            ->limit(20)
            ->get();

        // Group by maintenance type to find patterns
        $typeGroups = $maintenanceHistory->groupBy('maintenance_type');

        foreach ($typeGroups as $type => $records) {
            if ($records->count() >= 3 && $type === 'corrective') {
                // Frequent corrective maintenance indicates potential part issues
                $lastOccurrence = $records->first();
                $daysSinceLast = now()->diffInDays($lastOccurrence->completion_date);

                // Calculate average interval between failures
                $intervals = [];
                for ($i = 1; $i < $records->count(); $i++) {
                    $intervals[] = $records[$i - 1]->completion_date->diffInDays($records[$i]->completion_date);
                }

                $avgInterval = array_sum($intervals) / count($intervals);

                // Predict next failure with confidence based on pattern consistency
                $variance = $this->calculateVariance($intervals);
                $confidence = $variance < $avgInterval * 0.3 ? 'high' : 'medium';

                if ($daysSinceLast >= $avgInterval * 0.7) {
                    $prediction = $this->createPrediction($vehicle, [
                        'prediction_type' => 'part_failure',
                        'title' => 'Potential Part Failure Risk',
                        'description' => "Pattern of recurring {$type} maintenance detected. Next issue predicted based on historical interval.",
                        'confidence' => $confidence,
                        'priority' => 'high',
                        'predicted_date' => $lastOccurrence->completion_date->addDays($avgInterval),
                        'algorithm_used' => 'pattern_matching',
                        'algorithm_params' => [
                            'occurrence_count' => $records->count(),
                            'avg_interval_days' => $avgInterval,
                            'pattern_variance' => $variance,
                        ],
                        'estimated_cost_min' => 300,
                        'estimated_cost_max' => 1500,
                        'preventive_measures' => [
                            'Inspect related components proactively',
                            'Consider upgrading to higher quality parts',
                            'Monitor for early warning signs',
                        ],
                    ]);

                    $predictions->push($prediction);
                }
            }
        }

        return $predictions;
    }

    /**
     * Predict cost overruns based on spending trends
     */
    protected function predictCostOverruns(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Get last 6 months of costs
        $sixMonthsAgo = now()->subMonths(6);
        $costs = Cost::where('vehicle_id', $vehicle->id)
            ->where('cost_date', '>=', $sixMonthsAgo)
            ->orderBy('cost_date')
            ->get();

        if ($costs->count() < 3) {
            return $predictions; // Not enough data
        }

        // Calculate monthly totals
        $monthlyTotals = $costs->groupBy(function ($cost) {
            return $cost->cost_date->format('Y-m');
        })->map(function ($monthlyCosts) {
            return $monthlyCosts->sum('amount');
        });

        if ($monthlyTotals->count() < 3) {
            return $predictions;
        }

        // Calculate average and trend
        $values = $monthlyTotals->values()->toArray();
        $avgMonthlyCost = array_sum($values) / count($values);

        // Simple trend: compare last month to average
        $lastMonthCost = end($values);
        $trend = $lastMonthCost / $avgMonthlyCost;

        // If trend is increasing significantly (>1.3x average)
        if ($trend > 1.3) {
            $predictedNextMonth = $lastMonthCost * $trend;

            $prediction = $this->createPrediction($vehicle, [
                'prediction_type' => 'cost_overrun',
                'title' => 'Cost Overrun Risk Detected',
                'description' => "Vehicle costs are trending upward. Last month: ".number_format($lastMonthCost, 2)." EUR vs average: ".number_format($avgMonthlyCost, 2).' EUR.',
                'confidence' => 'medium',
                'priority' => $trend > 1.5 ? 'high' : 'medium',
                'predicted_date' => now()->endOfMonth()->addDay(),
                'algorithm_used' => 'trend_analysis',
                'algorithm_params' => [
                    'avg_monthly_cost' => $avgMonthlyCost,
                    'last_month_cost' => $lastMonthCost,
                    'trend_multiplier' => $trend,
                    'months_analyzed' => count($values),
                ],
                'estimated_cost_min' => $predictedNextMonth * 0.8,
                'estimated_cost_max' => $predictedNextMonth * 1.2,
                'estimated_cost_avg' => $predictedNextMonth,
                'recommended_actions' => [
                    'Review recent maintenance and repair costs',
                    'Consider cost-benefit analysis for continued operation',
                    'Evaluate if vehicle should be replaced',
                    'Investigate root causes of cost increases',
                ],
            ]);

            $predictions->push($prediction);
        }

        return $predictions;
    }

    /**
     * Predict fuel efficiency drop
     */
    protected function predictFuelEfficiencyDrop(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Get fuel transactions from last 6 months
        $fuelHistory = FuelTransaction::where('vehicle_id', $vehicle->id)
            ->where('transaction_date', '>=', now()->subMonths(6))
            ->whereNotNull('odometer_current')
            ->whereNotNull('odometer_previous')
            ->orderBy('transaction_date')
            ->get();

        if ($fuelHistory->count() < 5) {
            return $predictions; // Not enough data
        }

        // Calculate consumption for each transaction
        $consumptions = $fuelHistory->map(function ($transaction) {
            $distance = $transaction->odometer_current - $transaction->odometer_previous;

            return $distance > 0 ? ($transaction->quantity_liters / $distance) * 100 : null;
        })->filter()->values();

        if ($consumptions->count() < 5) {
            return $predictions;
        }

        // Calculate baseline (first half of data) vs recent (second half)
        $midpoint = (int) ($consumptions->count() / 2);
        $baseline = $consumptions->slice(0, $midpoint)->avg();
        $recent = $consumptions->slice($midpoint)->avg();

        // Detect significant degradation (>15% increase in consumption)
        $degradation = ($recent - $baseline) / $baseline;

        if ($degradation > 0.15) {
            $prediction = $this->createPrediction($vehicle, [
                'prediction_type' => 'fuel_efficiency_drop',
                'title' => 'Fuel Efficiency Degradation Detected',
                'description' => sprintf(
                    'Fuel consumption increased by %.1f%%. Baseline: %.2f L/100km, Recent: %.2f L/100km.',
                    $degradation * 100,
                    $baseline,
                    $recent
                ),
                'confidence' => 'high',
                'priority' => $degradation > 0.25 ? 'high' : 'medium',
                'predicted_date' => now()->addDays(14),
                'algorithm_used' => 'trend_analysis',
                'algorithm_params' => [
                    'baseline_consumption' => $baseline,
                    'recent_consumption' => $recent,
                    'degradation_percent' => $degradation * 100,
                    'transactions_analyzed' => $consumptions->count(),
                ],
                'recommended_actions' => [
                    'Check tire pressure',
                    'Inspect air filter',
                    'Check for engine issues',
                    'Verify fuel injectors are clean',
                    'Review driving patterns',
                ],
                'preventive_measures' => [
                    'Regular engine tune-ups',
                    'Maintain proper tire pressure',
                    'Use recommended fuel grade',
                ],
            ]);

            $predictions->push($prediction);
        }

        return $predictions;
    }

    /**
     * Predict tire replacement
     */
    protected function predictTireReplacement(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        // Standard tire lifespan: 40,000-50,000 km
        $avgTireLifespan = 45000;

        // Find last tire replacement
        $lastTireChange = Maintenance::where('vehicle_id', $vehicle->id)
            ->where(function ($query) {
                $query->where('maintenance_type', 'tire_replacement')
                    ->orWhere('description', 'like', '%tire%')
                    ->orWhere('description', 'like', '%pneu%');
            })
            ->where('status', 'completed')
            ->latest('completion_date')
            ->first();

        if ($lastTireChange && $vehicle->current_mileage_km) {
            $kmSinceTireChange = $vehicle->current_mileage_km - ($lastTireChange->odometer_reading ?? 0);

            if ($kmSinceTireChange >= $avgTireLifespan * 0.8) {
                $kmRemaining = $avgTireLifespan - $kmSinceTireChange;
                $avgDailyKm = $this->calculateAverageDailyMileage($vehicle);
                $daysUntilReplacement = $avgDailyKm > 0 ? $kmRemaining / $avgDailyKm : 60;

                $prediction = $this->createPrediction($vehicle, [
                    'prediction_type' => 'tire_replacement',
                    'title' => 'Tire Replacement Recommended',
                    'description' => sprintf(
                        'Tires have covered %s km since last replacement. Standard lifespan: %s km.',
                        number_format($kmSinceTireChange),
                        number_format($avgTireLifespan)
                    ),
                    'confidence' => 'medium',
                    'priority' => $kmRemaining <= 5000 ? 'high' : 'medium',
                    'predicted_date' => now()->addDays(max(0, $daysUntilReplacement)),
                    'current_odometer_km' => $vehicle->current_mileage_km,
                    'predicted_odometer_km' => $vehicle->current_mileage_km + $kmRemaining,
                    'algorithm_used' => 'mileage_based',
                    'algorithm_params' => [
                        'km_since_last_change' => $kmSinceTireChange,
                        'standard_lifespan_km' => $avgTireLifespan,
                        'avg_daily_km' => $avgDailyKm,
                    ],
                    'estimated_cost_min' => 300,
                    'estimated_cost_max' => 800,
                    'estimated_cost_avg' => 550,
                    'recommended_actions' => [
                        'Inspect tire tread depth',
                        'Check for uneven wear',
                        'Verify tire pressure',
                        'Get quotes from tire suppliers',
                    ],
                ]);

                $predictions->push($prediction);
            }
        }

        return $predictions;
    }

    /**
     * Predict contract expiry
     */
    protected function predictContractExpiry(Vehicle $vehicle): Collection
    {
        $predictions = collect();

        $expiringContracts = Contract::where('vehicle_id', $vehicle->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addDays(90))
            ->get();

        foreach ($expiringContracts as $contract) {
            $daysUntilExpiry = now()->diffInDays($contract->end_date);

            $priority = match (true) {
                $daysUntilExpiry <= 14 => 'critical',
                $daysUntilExpiry <= 30 => 'high',
                default => 'medium',
            };

            $prediction = $this->createPrediction($vehicle, [
                'prediction_type' => 'contract_expiry',
                'title' => "{$contract->contract_type} Contract Expiring",
                'description' => "Contract #{$contract->contract_number} expires in {$daysUntilExpiry} days.",
                'confidence' => 'very_high',
                'priority' => $priority,
                'predicted_date' => $contract->end_date,
                'related_contract_id' => $contract->id,
                'algorithm_used' => 'time_based',
                'estimated_cost_min' => $contract->monthly_cost * 12,
                'estimated_cost_max' => $contract->monthly_cost * 12 * 1.1,
                'estimated_cost_avg' => $contract->monthly_cost * 12,
                'recommended_actions' => [
                    'Review contract terms',
                    'Request renewal quotes',
                    'Compare with competitors',
                    'Decide on renewal or replacement',
                ],
            ]);

            $predictions->push($prediction);
        }

        return $predictions;
    }

    /**
     * Helper: Create a prediction record
     */
    protected function createPrediction(Vehicle $vehicle, array $data): MaintenancePrediction
    {
        return MaintenancePrediction::create(array_merge([
            'organization_id' => $vehicle->organization_id,
            'vehicle_id' => $vehicle->id,
        ], $data));
    }

    /**
     * Helper: Calculate average daily mileage
     */
    protected function calculateAverageDailyMileage(Vehicle $vehicle): float
    {
        if (!$vehicle->purchase_date || !$vehicle->current_mileage_km) {
            return 50; // Default estimate
        }

        $daysSincePurchase = now()->diffInDays($vehicle->purchase_date);

        if ($daysSincePurchase <= 0) {
            return 50;
        }

        return $vehicle->current_mileage_km / $daysSincePurchase;
    }

    /**
     * Helper: Calculate variance of an array
     */
    protected function calculateVariance(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(fn ($x) => pow($x - $mean, 2), $values);

        return sqrt(array_sum($squaredDifferences) / count($values));
    }

    /**
     * Get prediction statistics for organization
     */
    public function getOrganizationStatistics(int $organizationId): array
    {
        $predictions = MaintenancePrediction::where('organization_id', $organizationId)->get();

        return [
            'total_predictions' => $predictions->count(),
            'by_status' => [
                'pending' => $predictions->where('status', 'pending')->count(),
                'acknowledged' => $predictions->where('status', 'acknowledged')->count(),
                'scheduled' => $predictions->where('status', 'scheduled')->count(),
                'completed' => $predictions->where('status', 'completed')->count(),
                'dismissed' => $predictions->where('status', 'dismissed')->count(),
            ],
            'by_priority' => [
                'critical' => $predictions->where('priority', 'critical')->count(),
                'high' => $predictions->where('priority', 'high')->count(),
                'medium' => $predictions->where('priority', 'medium')->count(),
                'low' => $predictions->where('priority', 'low')->count(),
            ],
            'by_type' => $predictions->groupBy('prediction_type')->map->count(),
            'overdue' => $predictions->filter(fn ($p) => $p->isOverdue())->count(),
            'due_soon' => $predictions->filter(fn ($p) => $p->isDueSoon())->count(),
            'high_confidence' => $predictions->filter(fn ($p) => $p->isHighConfidence())->count(),
            'estimated_total_cost' => [
                'min' => $predictions->sum('estimated_cost_min'),
                'max' => $predictions->sum('estimated_cost_max'),
                'avg' => $predictions->sum('estimated_cost_avg'),
            ],
        ];
    }
}
