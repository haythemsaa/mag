<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargingStationRequest;
use App\Http\Requests\UpdateChargingStationRequest;
use App\Http\Resources\ChargingStationResource;
use App\Models\ChargingStation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Charging Stations
 *
 * APIs for managing EV charging stations
 */
class ChargingStationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ChargingStation::class, 'charging_station');
    }

    /**
     * List all charging stations
     *
     * @queryParam type string Filter by station type. Example: depot
     * @queryParam status string Filter by status. Example: available
     * @queryParam is_active boolean Filter by active status. Example: 1
     * @queryParam search string Search in name, address. Example: Main
     *
     * @response 200 {
     *   "data": [{
     *     "id": 1,
     *     "name": "Main Depot Station",
     *     "type": "depot",
     *     "status": "available"
     *   }]
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChargingStation::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['organization', 'site']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('address', 'ilike', "%{$search}%")
                  ->orWhere('station_code', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->input('per_page', 15), 100);
        $stations = $query->paginate($perPage);

        return ChargingStationResource::collection($stations);
    }

    /**
     * Create charging station
     *
     * @bodyParam name string required Station name. Example: Main Depot Charger
     * @bodyParam type string required Station type. Example: depot
     * @bodyParam connector_type string required Connector type. Example: Type 2
     * @bodyParam max_power_kw numeric required Max power in kW. Example: 22
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "name": "Main Depot Charger",
     *     "station_code": "CHG-001"
     *   }
     * }
     */
    public function store(StoreChargingStationRequest $request): ChargingStationResource
    {
        $station = ChargingStation::create($request->validated());

        return new ChargingStationResource($station);
    }

    /**
     * Get charging station details
     *
     * @urlParam charging_station integer required Station ID. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Main Depot Charger",
     *     "total_sessions": 150
     *   }
     * }
     */
    public function show(ChargingStation $chargingStation): ChargingStationResource
    {
        return new ChargingStationResource($chargingStation->load(['organization', 'site']));
    }

    /**
     * Update charging station
     *
     * @urlParam charging_station integer required Station ID. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "status": "maintenance"
     *   }
     * }
     */
    public function update(UpdateChargingStationRequest $request, ChargingStation $chargingStation): ChargingStationResource
    {
        $chargingStation->update($request->validated());

        return new ChargingStationResource($chargingStation);
    }

    /**
     * Delete charging station
     *
     * @urlParam charging_station integer required Station ID. Example: 1
     *
     * @response 204
     */
    public function destroy(ChargingStation $chargingStation): JsonResponse
    {
        $chargingStation->delete();

        return response()->json(null, 204);
    }

    /**
     * Get charging station statistics
     *
     * @urlParam charging_station integer required Station ID. Example: 1
     * @queryParam from_date date Start date. Example: 2025-01-01
     * @queryParam to_date date End date. Example: 2025-01-31
     *
     * @response 200 {
     *   "data": {
     *     "total_sessions": 150,
     *     "total_energy_kwh": 2500.50,
     *     "total_revenue": 375.25
     *   }
     * }
     */
    public function statistics(Request $request, ChargingStation $chargingStation): JsonResponse
    {
        $this->authorize('view', $chargingStation);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $sessionsQuery = $chargingStation->chargingSessions();

        if ($fromDate) {
            $sessionsQuery->where('start_time', '>=', $fromDate);
        }

        if ($toDate) {
            $sessionsQuery->where('start_time', '<=', $toDate);
        }

        $stats = [
            'total_sessions' => $sessionsQuery->count(),
            'completed_sessions' => (clone $sessionsQuery)->completed()->count(),
            'total_energy_kwh' => (clone $sessionsQuery)->sum('energy_delivered_kwh'),
            'total_revenue' => (clone $sessionsQuery)->sum('total_cost'),
            'average_session_duration' => (clone $sessionsQuery)->avg('duration_minutes'),
            'average_energy_per_session' => (clone $sessionsQuery)->avg('energy_delivered_kwh'),
        ];

        return response()->json(['data' => $stats]);
    }
}
