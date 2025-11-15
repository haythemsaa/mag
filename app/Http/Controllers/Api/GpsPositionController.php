<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GpsPosition;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class GpsPositionController extends Controller
{
    /**
     * Display a listing of GPS positions
     */
    public function index(Request $request): JsonResponse
    {
        $query = GpsPosition::with(['vehicle', 'driver']);

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by driver
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('recorded_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by recent positions (hours)
        if ($request->has('recent_hours')) {
            $query->recent($request->recent_hours);
        }

        // Filter by engine status
        if ($request->has('engine_on')) {
            $query->where('engine_on', $request->boolean('engine_on'));
        }

        // Filter by moving vehicles (speed > threshold)
        if ($request->has('moving') && $request->boolean('moving')) {
            $minSpeed = $request->get('min_speed', 5);
            $query->where('speed', '>', $minSpeed);
        }

        // Filter by speeding vehicles
        if ($request->has('speeding') && $request->boolean('speeding')) {
            $speedLimit = $request->get('speed_limit', 130);
            $query->where('speed', '>', $speedLimit);
        }

        // Filter by geographic bounds (bounding box)
        if ($request->has('min_lat') && $request->has('max_lat') &&
            $request->has('min_lon') && $request->has('max_lon')) {
            $query->whereBetween('latitude', [$request->min_lat, $request->max_lat])
                ->whereBetween('longitude', [$request->min_lon, $request->max_lon]);
        }

        // Pagination
        $perPage = $request->get('per_page', 50);
        $positions = $query->orderBy('recorded_at', 'desc')->paginate($perPage);

        return response()->json($positions);
    }

    /**
     * Store a newly created GPS position
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|integer|between:0,360',
            'direction' => 'nullable|in:N,NE,E,SE,S,SW,W,NW',
            'engine_on' => 'boolean',
            'engine_status' => 'nullable|in:running,idle,off',
            'fuel_level' => 'nullable|integer|between:0,100',
            'battery_level' => 'nullable|integer|between:0,100',
            'odometer' => 'nullable|integer|min:0',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'recorded_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['recorded_at'] = $data['recorded_at'] ?? now();

        // Auto-detect direction from heading if not provided
        if (!isset($data['direction']) && isset($data['heading'])) {
            $data['direction'] = $this->headingToDirection($data['heading']);
        }

        // Auto-detect engine status if not provided
        if (!isset($data['engine_status'])) {
            $engineOn = $data['engine_on'] ?? false;
            $speed = $data['speed'] ?? 0;

            if (!$engineOn) {
                $data['engine_status'] = 'off';
            } elseif ($speed > 5) {
                $data['engine_status'] = 'running';
            } else {
                $data['engine_status'] = 'idle';
            }
        }

        $position = GpsPosition::create($data);
        $position->load(['vehicle', 'driver']);

        return response()->json([
            'message' => 'GPS position created successfully',
            'data' => $position
        ], 201);
    }

    /**
     * Display the specified GPS position
     */
    public function show(string $id): JsonResponse
    {
        $position = GpsPosition::with(['vehicle', 'driver'])->findOrFail($id);

        // Add calculated fields
        $position->is_moving = $position->isMoving();
        $position->is_speeding = $position->isSpeeding();

        return response()->json([
            'data' => $position
        ]);
    }

    /**
     * Update GPS position (rarely used, mainly for corrections)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $position = GpsPosition::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only allow updating address information (for corrections)
        $position->update($request->only(['address', 'city', 'country']));

        return response()->json([
            'message' => 'GPS position updated successfully',
            'data' => $position
        ]);
    }

    /**
     * Remove GPS position
     */
    public function destroy(string $id): JsonResponse
    {
        $position = GpsPosition::findOrFail($id);
        $position->delete();

        return response()->json([
            'message' => 'GPS position deleted successfully'
        ]);
    }

    /**
     * Get latest position for a vehicle
     */
    public function latest(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $position = GpsPosition::where('vehicle_id', $vehicleId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$position) {
            return response()->json([
                'message' => 'No GPS position found for this vehicle',
                'vehicle_id' => $vehicleId
            ], 404);
        }

        $position->load(['vehicle', 'driver']);
        $position->is_moving = $position->isMoving();
        $position->is_speeding = $position->isSpeeding();

        return response()->json([
            'data' => $position
        ]);
    }

    /**
     * Get tracking history for a vehicle
     */
    public function track(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $query = GpsPosition::where('vehicle_id', $vehicleId);

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('recorded_at', [
                $request->start_date,
                $request->end_date
            ]);
        } else {
            // Default to last 24 hours
            $query->where('recorded_at', '>=', now()->subHours(24));
        }

        $positions = $query->orderBy('recorded_at', 'asc')->get();

        // Calculate statistics
        $stats = [
            'total_positions' => $positions->count(),
            'distance_traveled' => $this->calculateDistance($positions),
            'average_speed' => $positions->where('speed', '>', 0)->avg('speed'),
            'max_speed' => $positions->max('speed'),
            'time_moving' => $positions->where('speed', '>', 5)->count() * 5, // Assume 5 min intervals
            'time_stopped' => $positions->where('speed', '<=', 5)->count() * 5,
            'fuel_consumed' => $this->calculateFuelConsumed($positions),
            'speeding_incidents' => $positions->where('speed', '>', 130)->count(),
        ];

        return response()->json([
            'vehicle' => $vehicle,
            'positions' => $positions,
            'statistics' => $stats,
            'period' => [
                'start' => $positions->first()->recorded_at ?? null,
                'end' => $positions->last()->recorded_at ?? null,
            ]
        ]);
    }

    /**
     * Get live tracking for all vehicles in organization
     */
    public function liveTracking(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        // Get all vehicles for the organization
        $vehicles = Vehicle::where('organization_id', $organizationId)->get();

        $vehicleIds = $vehicles->pluck('id');

        // Get latest position for each vehicle
        $positions = GpsPosition::with(['vehicle', 'driver'])
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereIn('id', function ($query) use ($vehicleIds) {
                $query->selectRaw('MAX(id)')
                    ->from('gps_positions')
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->groupBy('vehicle_id');
            })
            ->get();

        // Add calculated fields
        $positions = $positions->map(function ($position) {
            $position->is_moving = $position->isMoving();
            $position->is_speeding = $position->isSpeeding();
            $position->age_minutes = $position->recorded_at->diffInMinutes(now());
            return $position;
        });

        // Calculate fleet statistics
        $stats = [
            'total_vehicles' => $vehicles->count(),
            'vehicles_with_position' => $positions->count(),
            'vehicles_moving' => $positions->where('is_moving', true)->count(),
            'vehicles_stopped' => $positions->where('is_moving', false)->count(),
            'vehicles_speeding' => $positions->where('is_speeding', true)->count(),
            'average_fuel_level' => round($positions->avg('fuel_level'), 1),
            'vehicles_low_fuel' => $positions->where('fuel_level', '<', 20)->count(),
        ];

        return response()->json([
            'data' => $positions,
            'statistics' => $stats,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get geofence alerts (vehicles outside defined areas)
     */
    public function geofenceAlerts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'center_lat' => 'required|numeric|between:-90,90',
            'center_lon' => 'required|numeric|between:-180,180',
            'radius_km' => 'required|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $organizationId = $request->organization_id;
        $centerLat = $request->center_lat;
        $centerLon = $request->center_lon;
        $radiusKm = $request->radius_km;

        // Get vehicles for the organization
        $vehicles = Vehicle::where('organization_id', $organizationId)->get();
        $vehicleIds = $vehicles->pluck('id');

        // Get latest positions
        $positions = GpsPosition::with(['vehicle', 'driver'])
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('recorded_at', '>=', now()->subHours(1))
            ->whereIn('id', function ($query) use ($vehicleIds) {
                $query->selectRaw('MAX(id)')
                    ->from('gps_positions')
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->groupBy('vehicle_id');
            })
            ->get();

        // Filter vehicles outside geofence
        $outsideGeofence = $positions->filter(function ($position) use ($centerLat, $centerLon, $radiusKm) {
            $distance = $this->calculateDistanceFromPoint(
                $position->latitude,
                $position->longitude,
                $centerLat,
                $centerLon
            );
            return $distance > $radiusKm;
        });

        return response()->json([
            'data' => $outsideGeofence->values(),
            'total_alerts' => $outsideGeofence->count(),
            'geofence' => [
                'center_lat' => $centerLat,
                'center_lon' => $centerLon,
                'radius_km' => $radiusKm,
            ],
            'timestamp' => now(),
        ]);
    }

    /**
     * Convert heading to cardinal direction
     */
    private function headingToDirection(int $heading): string
    {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) round($heading / 45) % 8;
        return $directions[$index];
    }

    /**
     * Calculate total distance from position history
     */
    private function calculateDistance($positions): float
    {
        if ($positions->count() < 2) {
            return 0;
        }

        $totalDistance = 0;
        for ($i = 1; $i < $positions->count(); $i++) {
            $prev = $positions[$i - 1];
            $current = $positions[$i];

            $distance = $this->calculateDistanceFromPoint(
                $prev->latitude,
                $prev->longitude,
                $current->latitude,
                $current->longitude
            );

            $totalDistance += $distance;
        }

        return round($totalDistance, 2);
    }

    /**
     * Calculate distance between two GPS points using Haversine formula
     */
    private function calculateDistanceFromPoint($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Calculate fuel consumed from position history
     */
    private function calculateFuelConsumed($positions): ?float
    {
        if ($positions->count() < 2) {
            return null;
        }

        $firstFuel = $positions->first()->fuel_level;
        $lastFuel = $positions->last()->fuel_level;

        if (!$firstFuel || !$lastFuel) {
            return null;
        }

        // Simple calculation: difference in fuel level percentage
        // This doesn't account for refueling
        $consumed = max(0, $firstFuel - $lastFuel);

        return round($consumed, 1);
    }
}
