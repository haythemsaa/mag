<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeofenceRequest;
use App\Http\Requests\UpdateGeofenceRequest;
use App\Http\Resources\GeofenceResource;
use App\Http\Resources\GeofenceEventResource;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Geofences
 *
 * APIs for managing geofences and zones
 */
class GeofenceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Geofence::class, 'geofence');
    }

    /**
     * List all geofences
     *
     * Get a paginated list of geofences with optional filters.
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam type string Filter by geofence type (authorized, forbidden, client_site, depot, parking, service_area, delivery_zone, restricted). Example: depot
     * @queryParam shape string Filter by shape (circle, polygon). Example: circle
     * @queryParam is_active boolean Filter by active status. Example: 1
     * @queryParam search string Search in name, description, address. Example: Main Depot
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "organization_id": 1,
     *       "name": "Main Depot",
     *       "description": "Company headquarters parking area",
     *       "type": "depot",
     *       "shape": "circle",
     *       "center_latitude": "48.8566000",
     *       "center_longitude": "2.3522000",
     *       "radius_meters": 500,
     *       "alert_on_entry": true,
     *       "alert_on_exit": true,
     *       "is_active": true,
     *       "color": "#3B82F6",
     *       "has_time_restrictions": false,
     *       "total_events_count": 245
     *     }
     *   ],
     *   "meta": {"current_page": 1, "total": 50}
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Geofence::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['organization']);

        // Filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('shape')) {
            $query->where('shape', $request->shape);
        }

        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->input('per_page', 15), 100);
        $geofences = $query->paginate($perPage);

        return GeofenceResource::collection($geofences);
    }

    /**
     * Create a new geofence
     *
     * @bodyParam name string required The name of the geofence. Example: Main Depot
     * @bodyParam description string Optional description. Example: Company headquarters parking
     * @bodyParam type string required Type of geofence. Example: depot
     * @bodyParam shape string required Shape type (circle or polygon). Example: circle
     * @bodyParam center_latitude numeric required for circle. Center latitude. Example: 48.8566
     * @bodyParam center_longitude numeric required for circle. Center longitude. Example: 2.3522
     * @bodyParam radius_meters integer required for circle. Radius in meters. Example: 500
     * @bodyParam polygon_coordinates array required for polygon. Array of lat/lng coordinates.
     * @bodyParam alert_on_entry boolean Send alert on entry. Example: true
     * @bodyParam alert_on_exit boolean Send alert on exit. Example: true
     * @bodyParam is_active boolean Is geofence active. Example: true
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "name": "Main Depot",
     *     "type": "depot",
     *     "shape": "circle"
     *   }
     * }
     */
    public function store(StoreGeofenceRequest $request): GeofenceResource
    {
        $geofence = Geofence::create($request->validated());

        return new GeofenceResource($geofence);
    }

    /**
     * Get geofence details
     *
     * @urlParam geofence integer required The ID of the geofence. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Main Depot",
     *     "statistics": {
     *       "total_events": 245,
     *       "entry_events": 128,
     *       "exit_events": 117
     *     }
     *   }
     * }
     */
    public function show(Geofence $geofence): GeofenceResource
    {
        return new GeofenceResource($geofence->load(['organization']));
    }

    /**
     * Update a geofence
     *
     * @urlParam geofence integer required The ID of the geofence. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Updated Depot",
     *     "is_active": false
     *   }
     * }
     */
    public function update(UpdateGeofenceRequest $request, Geofence $geofence): GeofenceResource
    {
        $geofence->update($request->validated());

        return new GeofenceResource($geofence);
    }

    /**
     * Delete a geofence
     *
     * @urlParam geofence integer required The ID of the geofence. Example: 1
     *
     * @response 204
     */
    public function destroy(Geofence $geofence): JsonResponse
    {
        $geofence->delete();

        return response()->json(null, 204);
    }

    /**
     * Get geofence events
     *
     * Get a list of entry/exit events for geofences.
     *
     * @queryParam geofence_id integer Filter by geofence ID. Example: 1
     * @queryParam vehicle_id integer Filter by vehicle ID. Example: 5
     * @queryParam driver_id integer Filter by driver ID. Example: 3
     * @queryParam event_type string Filter by event type (entry, exit). Example: entry
     * @queryParam from_date date Filter events from this date. Example: 2025-01-01
     * @queryParam to_date date Filter events to this date. Example: 2025-01-31
     * @queryParam alert_sent boolean Filter by alert status. Example: false
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "geofence_id": 1,
     *       "vehicle_id": 5,
     *       "event_type": "entry",
     *       "event_time": "2025-01-15 14:30:00",
     *       "alert_sent": true,
     *       "geofence": {"id": 1, "name": "Main Depot"}
     *     }
     *   ]
     * }
     */
    public function events(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Geofence::class);

        $query = GeofenceEvent::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['geofence', 'vehicle', 'driver']);

        // Filters
        if ($request->filled('geofence_id')) {
            $query->byGeofence($request->geofence_id);
        }

        if ($request->filled('vehicle_id')) {
            $query->byVehicle($request->vehicle_id);
        }

        if ($request->filled('driver_id')) {
            $query->byDriver($request->driver_id);
        }

        if ($request->filled('event_type')) {
            $query->byEventType($request->event_type);
        }

        if ($request->filled('from_date')) {
            $query->where('event_time', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('event_time', '<=', $request->to_date);
        }

        if ($request->has('alert_sent')) {
            $alertSent = filter_var($request->alert_sent, FILTER_VALIDATE_BOOLEAN);
            $query->where('alert_sent', $alertSent);
        }

        $query->orderBy('event_time', 'desc');

        $perPage = min($request->input('per_page', 15), 100);
        $events = $query->paginate($perPage);

        return GeofenceEventResource::collection($events);
    }

    /**
     * Get geofence statistics
     *
     * Get aggregated statistics for a specific geofence.
     *
     * @urlParam geofence integer required The ID of the geofence. Example: 1
     * @queryParam from_date date Start date for statistics. Example: 2025-01-01
     * @queryParam to_date date End date for statistics. Example: 2025-01-31
     *
     * @response 200 {
     *   "data": {
     *     "total_events": 245,
     *     "entry_events": 128,
     *     "exit_events": 117,
     *     "unique_vehicles": 15,
     *     "last_event": "2025-01-15 14:30:00",
     *     "events_by_day": [
     *       {"date": "2025-01-15", "count": 12}
     *     ],
     *     "top_vehicles": [
     *       {"vehicle_id": 5, "registration": "AB-123-CD", "events": 45}
     *     ]
     *   }
     * }
     */
    public function statistics(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('view', $geofence);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $eventsQuery = $geofence->events();

        if ($fromDate) {
            $eventsQuery->where('event_time', '>=', $fromDate);
        }

        if ($toDate) {
            $eventsQuery->where('event_time', '<=', $toDate);
        }

        $stats = [
            'total_events' => $eventsQuery->count(),
            'entry_events' => (clone $eventsQuery)->where('event_type', 'entry')->count(),
            'exit_events' => (clone $eventsQuery)->where('event_type', 'exit')->count(),
            'unique_vehicles' => (clone $eventsQuery)->distinct('vehicle_id')->count('vehicle_id'),
            'last_event' => (clone $eventsQuery)->latest('event_time')->first()?->event_time,

            // Events by day
            'events_by_day' => (clone $eventsQuery)
                ->selectRaw('DATE(event_time) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get(),

            // Top vehicles by event count
            'top_vehicles' => (clone $eventsQuery)
                ->selectRaw('vehicle_id, COUNT(*) as events')
                ->with('vehicle:id,registration_number')
                ->groupBy('vehicle_id')
                ->orderBy('events', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($event) {
                    return [
                        'vehicle_id' => $event->vehicle_id,
                        'registration' => $event->vehicle->registration_number,
                        'events' => $event->events,
                    ];
                }),
        ];

        return response()->json(['data' => $stats]);
    }
}
