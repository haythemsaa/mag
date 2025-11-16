<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargingSessionRequest;
use App\Http\Requests\UpdateChargingSessionRequest;
use App\Http\Resources\ChargingSessionResource;
use App\Models\ChargingSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Charging Sessions
 *
 * APIs for managing EV charging sessions
 */
class ChargingSessionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ChargingSession::class, 'charging_session');
    }

    /**
     * List all charging sessions
     *
     * @queryParam vehicle_id integer Filter by vehicle. Example: 1
     * @queryParam charging_station_id integer Filter by station. Example: 1
     * @queryParam status string Filter by status. Example: completed
     * @queryParam from_date date Start date. Example: 2025-01-01
     * @queryParam to_date date End date. Example: 2025-01-31
     * @queryParam search string Search in session number. Example: CHG
     *
     * @response 200 {
     *   "data": [{
     *     "id": 1,
     *     "session_number": "CHG-2025-000001",
     *     "status": "completed",
     *     "energy_delivered_kwh": 45.5
     *   }]
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChargingSession::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['vehicle', 'chargingStation', 'driver', 'organization']);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('charging_station_id')) {
            $query->where('charging_station_id', $request->charging_station_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('start_time', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('session_number', 'ilike', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->input('per_page', 15), 100);
        $sessions = $query->paginate($perPage);

        return ChargingSessionResource::collection($sessions);
    }

    /**
     * Create charging session
     *
     * @bodyParam vehicle_id integer required Vehicle ID. Example: 1
     * @bodyParam charging_station_id integer required Charging station ID. Example: 1
     * @bodyParam driver_id integer Driver ID. Example: 1
     * @bodyParam battery_level_start_percent integer required Starting battery level. Example: 20
     * @bodyParam cost_per_kwh numeric required Cost per kWh. Example: 0.15
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "session_number": "CHG-2025-000001",
     *     "status": "in_progress"
     *   }
     * }
     */
    public function store(StoreChargingSessionRequest $request): ChargingSessionResource
    {
        $session = ChargingSession::create($request->validated());

        return new ChargingSessionResource($session);
    }

    /**
     * Get charging session details
     *
     * @urlParam charging_session integer required Session ID. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "session_number": "CHG-2025-000001",
     *     "energy_delivered_kwh": 45.5,
     *     "total_cost": 6.83
     *   }
     * }
     */
    public function show(ChargingSession $chargingSession): ChargingSessionResource
    {
        return new ChargingSessionResource(
            $chargingSession->load(['vehicle', 'chargingStation', 'driver', 'organization'])
        );
    }

    /**
     * Update charging session
     *
     * @urlParam charging_session integer required Session ID. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "status": "interrupted"
     *   }
     * }
     */
    public function update(UpdateChargingSessionRequest $request, ChargingSession $chargingSession): ChargingSessionResource
    {
        $chargingSession->update($request->validated());

        return new ChargingSessionResource($chargingSession);
    }

    /**
     * Delete charging session
     *
     * @urlParam charging_session integer required Session ID. Example: 1
     *
     * @response 204
     */
    public function destroy(ChargingSession $chargingSession): JsonResponse
    {
        $chargingSession->delete();

        return response()->json(null, 204);
    }

    /**
     * Complete a charging session
     *
     * @urlParam charging_session integer required Session ID. Example: 1
     * @bodyParam battery_level_end_percent integer required Ending battery level. Example: 80
     * @bodyParam energy_delivered_kwh numeric required Energy delivered in kWh. Example: 45.5
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "status": "completed",
     *     "duration_minutes": 120,
     *     "total_cost": 6.83
     *   }
     * }
     */
    public function complete(Request $request, ChargingSession $chargingSession): ChargingSessionResource
    {
        $this->authorize('update', $chargingSession);

        $request->validate([
            'battery_level_end_percent' => 'required|integer|min:0|max:100',
            'energy_delivered_kwh' => 'required|numeric|min:0',
        ]);

        $chargingSession->update([
            'battery_level_end_percent' => $request->battery_level_end_percent,
            'energy_delivered_kwh' => $request->energy_delivered_kwh,
            'total_cost' => $request->energy_delivered_kwh * $chargingSession->cost_per_kwh,
        ]);

        $chargingSession->complete();

        return new ChargingSessionResource($chargingSession->fresh());
    }

    /**
     * Get charging sessions statistics
     *
     * @queryParam vehicle_id integer Filter by vehicle. Example: 1
     * @queryParam from_date date Start date. Example: 2025-01-01
     * @queryParam to_date date End date. Example: 2025-01-31
     *
     * @response 200 {
     *   "data": {
     *     "total_sessions": 150,
     *     "total_energy_kwh": 2500.50,
     *     "total_cost": 375.25,
     *     "average_session_duration": 90.5
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = ChargingSession::query()
            ->forOrganization($request->user()->organization_id);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('start_time', '<=', $request->to_date);
        }

        $stats = [
            'total_sessions' => $query->count(),
            'completed_sessions' => (clone $query)->completed()->count(),
            'in_progress_sessions' => (clone $query)->where('status', 'in_progress')->count(),
            'total_energy_kwh' => (clone $query)->sum('energy_delivered_kwh'),
            'total_cost' => (clone $query)->sum('total_cost'),
            'average_session_duration' => (clone $query)->avg('duration_minutes'),
            'average_energy_per_session' => (clone $query)->avg('energy_delivered_kwh'),
            'average_cost_per_session' => (clone $query)->avg('total_cost'),
        ];

        return response()->json(['data' => $stats]);
    }
}
