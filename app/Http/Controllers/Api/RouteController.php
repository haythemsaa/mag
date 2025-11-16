<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\Route;
use App\Services\RouteOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Routes
 *
 * APIs for managing routes and route optimization
 */
class RouteController extends Controller
{
    public function __construct(
        protected RouteOptimizationService $optimizationService
    ) {
    }

    /**
     * List routes
     *
     * Returns a paginated list of routes for the organization with filtering options.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (draft, planned, in_progress, completed, cancelled). Example: planned
     * @queryParam vehicle_id integer Filter by vehicle ID. Example: 1
     * @queryParam driver_id integer Filter by driver ID. Example: 2
     * @queryParam planned_date date Filter by planned date. Example: 2025-11-20
     * @queryParam is_optimized boolean Filter optimized routes only. Example: 1
     * @queryParam per_page integer Number of items per page. Defaults to 15. Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "route_number": "RT-2025-000001",
     *       "name": "Morning Deliveries - Paris",
     *       "status": "planned",
     *       "planned_date": "2025-11-20",
     *       "stops_count": 8,
     *       "planned_distance_km": 45.5,
     *       "planned_duration_minutes": 180,
     *       "is_optimized": true
     *     }
     *   ]
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Route::query()
            ->with(['vehicle', 'driver', 'organization'])
            ->forOrganization($request->user()->organization_id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('planned_date')) {
            $query->whereDate('planned_date', $request->planned_date);
        }

        if ($request->boolean('is_optimized')) {
            $query->optimized();
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ILIKE', '%' . $request->search . '%')
                    ->orWhere('route_number', 'ILIKE', '%' . $request->search . '%');
            });
        }

        $perPage = min($request->input('per_page', 15), 100);
        $routes = $query->orderBy('planned_date', 'desc')->paginate($perPage);

        return RouteResource::collection($routes);
    }

    /**
     * Create route
     *
     * Creates a new route with stops.
     *
     * @authenticated
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "route_number": "RT-2025-000001",
     *     "name": "Morning Deliveries",
     *     "status": "draft"
     *   }
     * }
     */
    public function store(StoreRouteRequest $request): RouteResource
    {
        $validated = $request->validated();
        $validated['organization_id'] = $request->user()->organization_id;

        $route = Route::create($validated);

        return new RouteResource($route->load(['vehicle', 'driver', 'stops']));
    }

    /**
     * Show route
     *
     * Returns detailed information about a specific route including all stops.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "route_number": "RT-2025-000001",
     *     "name": "Morning Deliveries",
     *     "status": "in_progress",
     *     "stops": [],
     *     "progress_percentage": 37.5
     *   }
     * }
     */
    public function show(Route $route): RouteResource
    {
        $this->authorize('view', $route);

        return new RouteResource($route->load(['vehicle', 'driver', 'stops', 'organization']));
    }

    /**
     * Update route
     *
     * Updates route details.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Updated Route Name"
     *   }
     * }
     */
    public function update(UpdateRouteRequest $request, Route $route): RouteResource
    {
        $route->update($request->validated());

        return new RouteResource($route->fresh(['vehicle', 'driver', 'stops']));
    }

    /**
     * Delete route
     *
     * Soft deletes a route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     *
     * @response 204
     */
    public function destroy(Route $route): JsonResponse
    {
        $this->authorize('delete', $route);

        $route->delete();

        return response()->json(null, 204);
    }

    /**
     * Start route
     *
     * Marks a route as in progress and records the start time.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     *
     * @response 200 {
     *   "message": "Route started successfully",
     *   "data": {
     *     "id": 1,
     *     "status": "in_progress",
     *     "started_at": "2025-11-16T08:00:00Z"
     *   }
     * }
     */
    public function start(Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        if (!$route->canStart()) {
            return response()->json([
                'message' => 'Route cannot be started. Ensure it has a vehicle, driver, and at least one stop.',
            ], 422);
        }

        $route->start();

        return response()->json([
            'message' => 'Route started successfully',
            'data' => new RouteResource($route->fresh()),
        ]);
    }

    /**
     * Complete route
     *
     * Marks a route as completed and calculates variance metrics.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @bodyParam actual_distance_km number optional Actual distance traveled. Example: 48.3
     * @bodyParam actual_duration_minutes integer optional Actual duration in minutes. Example: 195
     * @bodyParam actual_fuel_cost number optional Actual fuel cost. Example: 42.50
     * @bodyParam completion_notes string optional Completion notes. Example: All deliveries completed successfully
     *
     * @response 200 {
     *   "message": "Route completed successfully",
     *   "data": {
     *     "id": 1,
     *     "status": "completed",
     *     "distance_variance_km": 2.8,
     *     "distance_variance_percent": 6.15
     *   }
     * }
     */
    public function complete(Request $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        if (!$route->canComplete()) {
            return response()->json([
                'message' => 'Only routes in progress can be completed',
            ], 422);
        }

        $validated = $request->validate([
            'actual_distance_km' => 'nullable|numeric|min:0',
            'actual_duration_minutes' => 'nullable|integer|min:0',
            'actual_fuel_cost' => 'nullable|numeric|min:0',
            'actual_total_cost' => 'nullable|numeric|min:0',
            'completion_notes' => 'nullable|string|max:2000',
        ]);

        $route->complete($validated);

        return response()->json([
            'message' => 'Route completed successfully',
            'data' => new RouteResource($route->fresh()),
        ]);
    }

    /**
     * Cancel route
     *
     * Cancels a planned route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @bodyParam reason string optional Cancellation reason. Example: Customer cancelled all deliveries
     *
     * @response 200 {
     *   "message": "Route cancelled",
     *   "data": {
     *     "id": 1,
     *     "status": "cancelled"
     *   }
     * }
     */
    public function cancel(Request $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $route->cancel($validated['reason'] ?? null);

        return response()->json([
            'message' => 'Route cancelled',
            'data' => new RouteResource($route->fresh()),
        ]);
    }

    /**
     * Assign vehicle
     *
     * Assigns a vehicle to the route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @bodyParam vehicle_id integer required The vehicle ID. Example: 3
     *
     * @response 200 {
     *   "message": "Vehicle assigned successfully",
     *   "data": {
     *     "id": 1,
     *     "vehicle": {
     *       "id": 3,
     *       "registration_number": "AB-123-CD"
     *     }
     *   }
     * }
     */
    public function assignVehicle(Request $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $validated = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
        ]);

        $route->assignVehicle($validated['vehicle_id']);

        return response()->json([
            'message' => 'Vehicle assigned successfully',
            'data' => new RouteResource($route->fresh(['vehicle'])),
        ]);
    }

    /**
     * Assign driver
     *
     * Assigns a driver to the route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @bodyParam driver_id integer required The driver ID. Example: 2
     *
     * @response 200 {
     *   "message": "Driver assigned successfully",
     *   "data": {
     *     "id": 1,
     *     "driver": {
     *       "id": 2,
     *       "name": "John Doe"
     *     }
     *   }
     * }
     */
    public function assignDriver(Request $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
        ]);

        $route->assignDriver($validated['driver_id']);

        return response()->json([
            'message' => 'Driver assigned successfully',
            'data' => new RouteResource($route->fresh(['driver'])),
        ]);
    }

    /**
     * Optimize route
     *
     * Optimizes the route stop order using the specified algorithm.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @bodyParam method string optional Optimization method (nearest_neighbor, two_opt). Defaults to nearest_neighbor. Example: two_opt
     *
     * @response 200 {
     *   "message": "Route optimized successfully",
     *   "data": {
     *     "id": 1,
     *     "is_optimized": true,
     *     "optimization_method": "two_opt",
     *     "planned_distance_km": 42.3,
     *     "planned_duration_minutes": 165
     *   },
     *   "savings": {
     *     "distance_saved_km": 8.5,
     *     "time_saved_minutes": 25,
     *     "cost_saved": 12.75,
     *     "improvement_percent": 20
     *   }
     * }
     */
    public function optimize(Request $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $validated = $request->validate([
            'method' => 'nullable|string|in:nearest_neighbor,two_opt',
        ]);

        $method = $validated['method'] ?? 'nearest_neighbor';

        // Validate route can be optimized
        $validation = $this->optimizationService->canOptimize($route);

        if (!$validation['can_optimize']) {
            return response()->json([
                'message' => 'Route cannot be optimized',
                'errors' => $validation['errors'],
            ], 422);
        }

        // Optimize the route
        $optimizedRoute = $this->optimizationService->optimize($route, $method);

        // Calculate savings
        $savings = $this->optimizationService->calculateOptimizationSavings($optimizedRoute);

        return response()->json([
            'message' => 'Route optimized successfully',
            'data' => new RouteResource($optimizedRoute->fresh(['stops'])),
            'savings' => $savings,
        ]);
    }

    /**
     * Get statistics
     *
     * Returns route statistics for the organization.
     *
     * @authenticated
     *
     * @queryParam from_date date Filter statistics from this date. Example: 2025-11-01
     * @queryParam to_date date Filter statistics until this date. Example: 2025-11-16
     *
     * @response 200 {
     *   "data": {
     *     "total_routes": 125,
     *     "by_status": {
     *       "draft": 12,
     *       "planned": 25,
     *       "in_progress": 8,
     *       "completed": 75,
     *       "cancelled": 5
     *     },
     *     "total_distance_km": 8542.5,
     *     "total_stops": 1050,
     *     "average_stops_per_route": 8.4,
     *     "optimized_routes": 98,
     *     "optimization_rate_percent": 78.4
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = Route::forOrganization($request->user()->organization_id);

        if ($request->filled('from_date')) {
            $query->where('planned_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('planned_date', '<=', $request->to_date);
        }

        $routes = $query->get();

        $stats = [
            'total_routes' => $routes->count(),
            'by_status' => [
                'draft' => $routes->where('status', 'draft')->count(),
                'planned' => $routes->where('status', 'planned')->count(),
                'in_progress' => $routes->where('status', 'in_progress')->count(),
                'completed' => $routes->where('status', 'completed')->count(),
                'cancelled' => $routes->where('status', 'cancelled')->count(),
            ],
            'total_distance_km' => round($routes->sum('planned_distance_km'), 2),
            'total_duration_hours' => round($routes->sum('planned_duration_minutes') / 60, 1),
            'total_stops' => $routes->sum('stops_count'),
            'average_stops_per_route' => $routes->count() > 0 ? round($routes->avg('stops_count'), 1) : 0,
            'optimized_routes' => $routes->where('is_optimized', true)->count(),
            'optimization_rate_percent' => $routes->count() > 0 ? round(($routes->where('is_optimized', true)->count() / $routes->count()) * 100, 1) : 0,
            'total_estimated_cost' => round($routes->sum('estimated_total_cost'), 2),
            'total_actual_cost' => round($routes->sum('actual_total_cost'), 2),
        ];

        return response()->json(['data' => $stats]);
    }
}
