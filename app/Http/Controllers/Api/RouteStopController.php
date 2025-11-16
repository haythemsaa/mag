<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteStopRequest;
use App\Http\Requests\UpdateRouteStopRequest;
use App\Http\Resources\RouteStopResource;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Route Stops
 *
 * APIs for managing individual stops within routes
 */
class RouteStopController extends Controller
{
    /**
     * List stops for a route
     *
     * Returns all stops for a specific route in order.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @queryParam status string Filter by status. Example: pending
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "stop_number": 1,
     *       "location_name": "Client A",
     *       "status": "completed",
     *       "type": "delivery"
     *     }
     *   ]
     * }
     */
    public function index(Request $request, Route $route): AnonymousResourceCollection
    {
        $this->authorize('view', $route);

        $query = $route->stops();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $stops = $query->orderBy('stop_number')->get();

        return RouteStopResource::collection($stops);
    }

    /**
     * Create stop
     *
     * Adds a new stop to a route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "stop_number": 3,
     *     "location_name": "Client C"
     *   }
     * }
     */
    public function store(StoreRouteStopRequest $request, Route $route): RouteStopResource
    {
        $validated = $request->validated();
        $validated['route_id'] = $route->id;

        // Auto-assign stop number if not provided
        if (!isset($validated['stop_number'])) {
            $validated['stop_number'] = $route->stops()->count() + 1;
        }

        $stop = RouteStop::create($validated);

        // Update route metrics
        $route->updatePlannedMetrics();

        return new RouteStopResource($stop);
    }

    /**
     * Show stop
     *
     * Returns details of a specific stop.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     *
     * @response 200 {
     *   "data": {
     *     "id": 5,
     *     "stop_number": 2,
     *     "location_name": "Client B",
     *     "status": "in_progress"
     *   }
     * }
     */
    public function show(Route $route, RouteStop $stop): RouteStopResource
    {
        $this->authorize('view', $route);

        // Ensure stop belongs to route
        if ($stop->route_id !== $route->id) {
            abort(404, 'Stop not found in this route');
        }

        return new RouteStopResource($stop);
    }

    /**
     * Update stop
     *
     * Updates stop details.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     *
     * @response 200 {
     *   "data": {
     *     "id": 5,
     *     "location_name": "Updated Client Name"
     *   }
     * }
     */
    public function update(UpdateRouteStopRequest $request, Route $route, RouteStop $stop): RouteStopResource
    {
        // Ensure stop belongs to route
        if ($stop->route_id !== $route->id) {
            abort(404, 'Stop not found in this route');
        }

        $stop->update($request->validated());

        // Update route metrics if distance/duration changed
        $route->updatePlannedMetrics();

        return new RouteStopResource($stop->fresh());
    }

    /**
     * Delete stop
     *
     * Removes a stop from the route.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     *
     * @response 204
     */
    public function destroy(Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        // Ensure stop belongs to route
        if ($stop->route_id !== $route->id) {
            abort(404, 'Stop not found in this route');
        }

        $stop->delete();

        // Update route metrics
        $route->updatePlannedMetrics();

        return response()->json(null, 204);
    }

    /**
     * Mark as arrived
     *
     * Records arrival at a stop.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     *
     * @response 200 {
     *   "message": "Arrival recorded",
     *   "data": {
     *     "id": 5,
     *     "status": "arrived",
     *     "actual_arrival": "2025-11-16T10:30:00Z"
     *   }
     * }
     */
    public function arrive(Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        if (!$stop->isPending()) {
            return response()->json([
                'message' => 'Only pending stops can be marked as arrived',
            ], 422);
        }

        $stop->markAsArrived();

        return response()->json([
            'message' => 'Arrival recorded',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Start service
     *
     * Starts service at a stop.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     *
     * @response 200 {
     *   "message": "Service started",
     *   "data": {
     *     "id": 5,
     *     "status": "in_progress"
     *   }
     * }
     */
    public function startService(Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $stop->startService();

        return response()->json([
            'message' => 'Service started',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Complete stop
     *
     * Marks a stop as completed.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     * @bodyParam completion_notes string optional Completion notes. Example: Package delivered successfully
     * @bodyParam signature_path string optional Path to signature file. Example: /storage/signatures/sig123.png
     *
     * @response 200 {
     *   "message": "Stop completed",
     *   "data": {
     *     "id": 5,
     *     "status": "completed",
     *     "actual_departure": "2025-11-16T10:45:00Z"
     *   }
     * }
     */
    public function complete(Request $request, Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $validated = $request->validate([
            'completion_notes' => 'nullable|string|max:2000',
            'signature_path' => 'nullable|string',
        ]);

        $stop->completeStop($validated);

        return response()->json([
            'message' => 'Stop completed',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Skip stop
     *
     * Marks a stop as skipped with a reason.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     * @bodyParam reason string required Reason for skipping. Example: Customer not available
     *
     * @response 200 {
     *   "message": "Stop skipped",
     *   "data": {
     *     "id": 5,
     *     "status": "skipped",
     *     "failure_reason": "Customer not available"
     *   }
     * }
     */
    public function skip(Request $request, Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $stop->skipStop($validated['reason']);

        return response()->json([
            'message' => 'Stop skipped',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Fail stop
     *
     * Marks a stop as failed with a reason.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     * @bodyParam reason string required Reason for failure. Example: Access denied by security
     *
     * @response 200 {
     *   "message": "Stop marked as failed",
     *   "data": {
     *     "id": 5,
     *     "status": "failed",
     *     "failure_reason": "Access denied by security"
     *   }
     * }
     */
    public function fail(Request $request, Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $stop->failStop($validated['reason']);

        return response()->json([
            'message' => 'Stop marked as failed',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Upload signature
     *
     * Uploads a signature for the stop.
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     * @bodyParam signature file required Signature image file. Example: signature.png
     *
     * @response 200 {
     *   "message": "Signature uploaded",
     *   "data": {
     *     "id": 5,
     *     "signature_path": "/storage/signatures/sig123.png"
     *   }
     * }
     */
    public function uploadSignature(Request $request, Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $validated = $request->validate([
            'signature' => 'required|file|image|mimes:png,jpg,jpeg|max:5120', // 5MB max
        ]);

        $path = $request->file('signature')->store("route_stops/{$stop->id}/signatures", 'public');

        $stop->uploadSignature($path);

        return response()->json([
            'message' => 'Signature uploaded',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }

    /**
     * Upload photo
     *
     * Uploads a photo for the stop (proof of delivery, etc.).
     *
     * @authenticated
     *
     * @urlParam route integer required The ID of the route. Example: 1
     * @urlParam stop integer required The ID of the stop. Example: 5
     * @bodyParam photo file required Photo file. Example: delivery.jpg
     *
     * @response 200 {
     *   "message": "Photo uploaded",
     *   "data": {
     *     "id": 5,
     *     "photo_paths": ["/storage/photos/photo1.jpg", "/storage/photos/photo2.jpg"]
     *   }
     * }
     */
    public function uploadPhoto(Request $request, Route $route, RouteStop $stop): JsonResponse
    {
        $this->authorize('update', $route);

        if ($stop->route_id !== $route->id) {
            abort(404);
        }

        $validated = $request->validate([
            'photo' => 'required|file|image|mimes:png,jpg,jpeg|max:10240', // 10MB max
        ]);

        $path = $request->file('photo')->store("route_stops/{$stop->id}/photos", 'public');

        $stop->uploadPhoto($path);

        return response()->json([
            'message' => 'Photo uploaded',
            'data' => new RouteStopResource($stop->fresh()),
        ]);
    }
}
