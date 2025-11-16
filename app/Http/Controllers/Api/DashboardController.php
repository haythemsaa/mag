<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Cost;
use App\Models\Driver;
use App\Models\FuelTransaction;
use App\Models\GpsPosition;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard KPIs for an organization
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        // Get date range (default: current month)
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        return response()->json([
            'data' => [
                'fleet' => $this->getFleetKPIs($organizationId),
                'costs' => $this->getCostKPIs($organizationId, $startDate, $endDate),
                'maintenance' => $this->getMaintenanceKPIs($organizationId),
                'drivers' => $this->getDriverKPIs($organizationId),
                'fuel' => $this->getFuelKPIs($organizationId, $startDate, $endDate),
                'alerts' => $this->getAlerts($organizationId),
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get fleet KPIs
     */
    private function getFleetKPIs(int $organizationId): array
    {
        $vehicles = Vehicle::where('organization_id', $organizationId);

        return [
            'total_vehicles' => $vehicles->count(),
            'active_vehicles' => $vehicles->where('status', 'active')->count(),
            'inactive_vehicles' => $vehicles->where('status', 'inactive')->count(),
            'maintenance_vehicles' => $vehicles->where('status', 'maintenance')->count(),
            'by_fuel_type' => $vehicles->get()->groupBy('fuel_type')->map->count(),
            'by_type' => $vehicles->get()->groupBy('type')->map->count(),
            'average_age' => round($vehicles->whereNotNull('year')->avg(\DB::raw('EXTRACT(YEAR FROM NOW()) - year')), 1),
            'total_mileage' => $vehicles->sum('mileage'),
        ];
    }

    /**
     * Get cost KPIs
     */
    private function getCostKPIs(int $organizationId, $startDate, $endDate): array
    {
        $costs = Cost::where('organization_id', $organizationId)
            ->whereBetween('date', [$startDate, $endDate]);

        $totalCosts = $costs->sum('amount');
        $previousPeriodStart = now()->parse($startDate)->subMonth();
        $previousPeriodEnd = now()->parse($endDate)->subMonth();

        $previousCosts = Cost::where('organization_id', $organizationId)
            ->whereBetween('date', [$previousPeriodStart, $previousPeriodEnd])
            ->sum('amount');

        $trend = $previousCosts > 0 ? (($totalCosts - $previousCosts) / $previousCosts) * 100 : 0;

        return [
            'total_costs' => round($totalCosts, 2),
            'trend_percentage' => round($trend, 1),
            'by_category' => $costs->get()->groupBy('category')->map(fn($group) => round($group->sum('amount'), 2)),
            'pending_validation' => Cost::where('organization_id', $organizationId)
                ->where('validated', false)
                ->count(),
            'vat_deductible_total' => round($costs->where('vat_deductible', true)->sum('vat_amount'), 2),
        ];
    }

    /**
     * Get maintenance KPIs
     */
    private function getMaintenanceKPIs(int $organizationId): array
    {
        $maintenances = Maintenance::where('organization_id', $organizationId);

        return [
            'total_maintenances' => $maintenances->count(),
            'pending' => $maintenances->where('status', 'pending')->count(),
            'in_progress' => $maintenances->where('status', 'in_progress')->count(),
            'completed' => $maintenances->where('status', 'completed')->count(),
            'overdue' => Maintenance::where('organization_id', $organizationId)
                ->where('status', '!=', 'completed')
                ->where('scheduled_date', '<', now())
                ->count(),
            'upcoming_7_days' => Maintenance::where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->whereBetween('scheduled_date', [now(), now()->addDays(7)])
                ->count(),
            'average_cost' => round($maintenances->where('status', 'completed')->avg('total_cost'), 2),
        ];
    }

    /**
     * Get driver KPIs
     */
    private function getDriverKPIs(int $organizationId): array
    {
        $drivers = Driver::where('organization_id', $organizationId);

        return [
            'total_drivers' => $drivers->count(),
            'active_drivers' => $drivers->where('status', 'active')->count(),
            'inactive_drivers' => $drivers->where('status', 'inactive')->count(),
            'licenses_expiring_30_days' => Driver::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereBetween('license_expiry_date', [now(), now()->addDays(30)])
                ->count(),
            'average_eco_score' => round($drivers->where('status', 'active')->avg('eco_driving_score'), 1),
            'high_infractions' => $drivers->where('infractions_count', '>', 5)->count(),
        ];
    }

    /**
     * Get fuel KPIs
     */
    private function getFuelKPIs(int $organizationId, $startDate, $endDate): array
    {
        $transactions = FuelTransaction::where('organization_id', $organizationId)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        return [
            'total_volume' => round($transactions->sum('quantity'), 2),
            'total_cost' => round($transactions->sum('total_cost'), 2),
            'average_price_per_liter' => round($transactions->avg('unit_price'), 3),
            'transaction_count' => $transactions->count(),
            'anomalies_detected' => $transactions->where('anomaly_detected', true)->count(),
            'pending_validation' => $transactions->where('validated', false)->count(),
            'by_fuel_type' => $transactions->get()->groupBy('fuel_type')->map(fn($group) => [
                'volume' => round($group->sum('quantity'), 2),
                'cost' => round($group->sum('total_cost'), 2),
            ]),
        ];
    }

    /**
     * Get alerts and warnings
     */
    private function getAlerts(int $organizationId): array
    {
        return [
            'maintenance_overdue' => Maintenance::where('organization_id', $organizationId)
                ->where('status', '!=', 'completed')
                ->where('scheduled_date', '<', now())
                ->count(),
            'licenses_expiring_soon' => Driver::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereBetween('license_expiry_date', [now(), now()->addDays(30)])
                ->count(),
            'contracts_expiring_soon' => Contract::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->whereBetween('end_date', [now(), now()->addDays(30)])
                ->count(),
            'costs_pending_validation' => Cost::where('organization_id', $organizationId)
                ->where('validated', false)
                ->count(),
            'fuel_anomalies' => FuelTransaction::where('organization_id', $organizationId)
                ->where('anomaly_detected', true)
                ->where('validated', false)
                ->count(),
            'vehicles_needs_maintenance' => Vehicle::where('organization_id', $organizationId)
                ->where(function ($query) {
                    $query->whereRaw('mileage - last_maintenance_mileage >= maintenance_interval')
                        ->orWhereNull('last_maintenance_mileage');
                })
                ->count(),
        ];
    }

    /**
     * Get real-time fleet status
     */
    public function liveFleet(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        $vehicles = Vehicle::where('organization_id', $organizationId)->get();
        $vehicleIds = $vehicles->pluck('id');

        // Get latest GPS position for each vehicle
        $latestPositions = GpsPosition::with(['vehicle', 'driver'])
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereIn('id', function ($query) use ($vehicleIds) {
                $query->selectRaw('MAX(id)')
                    ->from('gps_positions')
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->groupBy('vehicle_id');
            })
            ->get();

        $positionsMap = $latestPositions->keyBy('vehicle_id');

        $fleet = $vehicles->map(function ($vehicle) use ($positionsMap) {
            $position = $positionsMap->get($vehicle->id);

            return [
                'vehicle_id' => $vehicle->id,
                'registration' => $vehicle->registration_number,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'driver' => $vehicle->currentDriver ? $vehicle->currentDriver->first_name . ' ' . $vehicle->currentDriver->last_name : null,
                'status' => $vehicle->status,
                'position' => $position ? [
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'speed' => $position->speed,
                    'heading' => $position->heading,
                    'engine_on' => $position->engine_on,
                    'fuel_level' => $position->fuel_level,
                    'last_update' => $position->recorded_at->toIso8601String(),
                    'age_minutes' => $position->recorded_at->diffInMinutes(now()),
                ] : null,
            ];
        });

        return response()->json([
            'data' => $fleet,
            'summary' => [
                'total' => $vehicles->count(),
                'with_position' => $latestPositions->count(),
                'moving' => $latestPositions->where('speed', '>', 5)->count(),
                'stationary' => $latestPositions->where('speed', '<=', 5)->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
