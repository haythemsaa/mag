<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTheftAlertRequest;
use App\Http\Requests\UpdateTheftAlertRequest;
use App\Http\Resources\TheftAlertResource;
use App\Models\TheftAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Theft Alerts
 *
 * APIs for managing theft alerts and security monitoring
 */
class TheftAlertController extends Controller
{
    /**
     * List theft alerts
     *
     * Returns a paginated list of theft alerts for the organization with filtering options.
     *
     * @authenticated
     *
     * @queryParam status string Filter by status (pending, investigating, false_alarm, confirmed_theft, resolved). Example: pending
     * @queryParam severity string Filter by severity (low, medium, high, critical). Example: critical
     * @queryParam type string Filter by alert type. Example: unauthorized_ignition
     * @queryParam vehicle_id integer Filter by vehicle ID. Example: 1
     * @queryParam unresolved boolean Filter for unresolved alerts only. Example: 1
     * @queryParam from_date date Filter alerts from this date. Example: 2025-11-01
     * @queryParam to_date date Filter alerts until this date. Example: 2025-11-16
     * @queryParam per_page integer Number of items per page. Defaults to 15. Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "alert_number": "TH-2025-000001",
     *       "type": "unauthorized_ignition",
     *       "status": "pending",
     *       "severity": "critical",
     *       "description": "Unauthorized ignition detected",
     *       "detected_at": "2025-11-16T02:30:00Z",
     *       "vehicle": {
     *         "id": 1,
     *         "registration_number": "AB-123-CD"
     *       }
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "total": 50
     *   }
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TheftAlert::query()
            ->with(['vehicle', 'geofence', 'assignedTo', 'organization'])
            ->forOrganization($request->user()->organization_id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->boolean('unresolved')) {
            $query->unresolved();
        }

        if ($request->filled('from_date')) {
            $query->where('detected_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('detected_at', '<=', $request->to_date);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $alerts = $query->orderBy('detected_at', 'desc')->paginate($perPage);

        return TheftAlertResource::collection($alerts);
    }

    /**
     * Create theft alert
     *
     * Creates a new theft alert (typically called by detection systems).
     *
     * @authenticated
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "alert_number": "TH-2025-000001",
     *     "type": "unauthorized_ignition",
     *     "status": "pending",
     *     "severity": "critical"
     *   }
     * }
     */
    public function store(StoreTheftAlertRequest $request): TheftAlertResource
    {
        $validated = $request->validated();
        $validated['organization_id'] = $request->user()->organization_id;

        $alert = TheftAlert::create($validated);

        return new TheftAlertResource($alert->load(['vehicle', 'geofence', 'assignedTo']));
    }

    /**
     * Show theft alert
     *
     * Returns detailed information about a specific theft alert.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "alert_number": "TH-2025-000001",
     *     "type": "unauthorized_ignition",
     *     "status": "investigating",
     *     "severity": "critical",
     *     "description": "Unauthorized ignition detected",
     *     "detected_at": "2025-11-16T02:30:00Z",
     *     "latitude": 48.8566,
     *     "longitude": 2.3522,
     *     "vehicle": {
     *       "id": 1,
     *       "registration_number": "AB-123-CD"
     *     },
     *     "assigned_to": {
     *       "id": 5,
     *       "name": "Security Manager"
     *     },
     *     "investigation_notes": "Contacted driver, awaiting response",
     *     "response_time_minutes": 12
     *   }
     * }
     */
    public function show(TheftAlert $theftAlert): TheftAlertResource
    {
        $this->authorize('view', $theftAlert);

        return new TheftAlertResource($theftAlert->load(['vehicle', 'geofence', 'assignedTo', 'organization']));
    }

    /**
     * Update theft alert
     *
     * Updates theft alert details (investigation notes, admin notes, etc.).
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "alert_number": "TH-2025-000001",
     *     "investigation_notes": "Updated investigation notes"
     *   }
     * }
     */
    public function update(UpdateTheftAlertRequest $request, TheftAlert $theftAlert): TheftAlertResource
    {
        $theftAlert->update($request->validated());

        return new TheftAlertResource($theftAlert->fresh(['vehicle', 'geofence', 'assignedTo']));
    }

    /**
     * Delete theft alert
     *
     * Soft deletes a theft alert.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     *
     * @response 204
     */
    public function destroy(TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('delete', $theftAlert);

        $theftAlert->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign alert to user
     *
     * Assigns a theft alert to a specific user for investigation.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam user_id integer required The user ID to assign to. Example: 5
     *
     * @response 200 {
     *   "message": "Alert assigned successfully",
     *   "data": {
     *     "id": 1,
     *     "assigned_to": {
     *       "id": 5,
     *       "name": "Security Manager"
     *     }
     *   }
     * }
     */
    public function assign(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);

        // Ensure user belongs to same organization
        if ($user->organization_id !== $theftAlert->organization_id) {
            return response()->json([
                'message' => 'User must belong to the same organization',
            ], 422);
        }

        $theftAlert->assign($user);

        return response()->json([
            'message' => 'Alert assigned successfully',
            'data' => new TheftAlertResource($theftAlert->fresh(['assignedTo'])),
        ]);
    }

    /**
     * Start investigation
     *
     * Marks a theft alert as being actively investigated.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     *
     * @response 200 {
     *   "message": "Investigation started",
     *   "data": {
     *     "id": 1,
     *     "status": "investigating",
     *     "investigation_started_at": "2025-11-16T03:00:00Z"
     *   }
     * }
     */
    public function investigate(TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        if ($theftAlert->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending alerts can be moved to investigation',
            ], 422);
        }

        $theftAlert->startInvestigation();

        return response()->json([
            'message' => 'Investigation started',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Mark as false alarm
     *
     * Marks a theft alert as a false alarm and resolves it.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam resolution_notes string Optional notes about the resolution. Example: Vehicle was used by authorized driver
     *
     * @response 200 {
     *   "message": "Alert marked as false alarm",
     *   "data": {
     *     "id": 1,
     *     "status": "false_alarm",
     *     "resolved_at": "2025-11-16T04:00:00Z"
     *   }
     * }
     */
    public function markFalseAlarm(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if ($theftAlert->isResolved()) {
            return response()->json([
                'message' => 'Alert is already resolved',
            ], 422);
        }

        $theftAlert->markAsFalseAlarm($validated['resolution_notes'] ?? null);

        return response()->json([
            'message' => 'Alert marked as false alarm',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Confirm theft
     *
     * Confirms a theft alert as an actual theft incident.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam resolution_notes string Optional notes about the confirmation. Example: Theft confirmed by driver, police notified
     *
     * @response 200 {
     *   "message": "Theft confirmed",
     *   "data": {
     *     "id": 1,
     *     "status": "confirmed_theft",
     *     "resolution_notes": "Theft confirmed by driver"
     *   }
     * }
     */
    public function confirmTheft(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $theftAlert->confirmTheft($validated['resolution_notes'] ?? null);

        return response()->json([
            'message' => 'Theft confirmed',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Resolve alert
     *
     * Marks a theft alert as resolved (vehicle recovered or issue resolved).
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam resolution_notes string Optional notes about the resolution. Example: Vehicle recovered by police
     *
     * @response 200 {
     *   "message": "Alert resolved",
     *   "data": {
     *     "id": 1,
     *     "status": "resolved",
     *     "resolved_at": "2025-11-17T10:00:00Z"
     *   }
     * }
     */
    public function resolve(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        if ($theftAlert->isResolved()) {
            return response()->json([
                'message' => 'Alert is already resolved',
            ], 422);
        }

        $theftAlert->markAsResolved($validated['resolution_notes'] ?? null);

        return response()->json([
            'message' => 'Alert resolved',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Notify police
     *
     * Records that police have been notified about the theft alert.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam police_reference_number string Optional police reference number. Example: POL-2025-12345
     *
     * @response 200 {
     *   "message": "Police notification recorded",
     *   "data": {
     *     "id": 1,
     *     "police_notified": true,
     *     "police_notified_at": "2025-11-16T03:30:00Z",
     *     "police_reference_number": "POL-2025-12345"
     *   }
     * }
     */
    public function notifyPolice(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'police_reference_number' => 'nullable|string|max:255',
        ]);

        if ($theftAlert->isPoliceNotified()) {
            return response()->json([
                'message' => 'Police already notified for this alert',
            ], 422);
        }

        $theftAlert->notifyPolice($validated['police_reference_number'] ?? null);

        return response()->json([
            'message' => 'Police notification recorded',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Link insurance claim
     *
     * Links an insurance claim number to the theft alert.
     *
     * @authenticated
     *
     * @urlParam theftAlert integer required The ID of the theft alert. Example: 1
     * @bodyParam insurance_claim_number string required Insurance claim number. Example: INS-2025-98765
     *
     * @response 200 {
     *   "message": "Insurance claim linked",
     *   "data": {
     *     "id": 1,
     *     "insurance_claim_number": "INS-2025-98765"
     *   }
     * }
     */
    public function linkInsuranceClaim(Request $request, TheftAlert $theftAlert): JsonResponse
    {
        $this->authorize('update', $theftAlert);

        $validated = $request->validate([
            'insurance_claim_number' => 'required|string|max:255',
        ]);

        $theftAlert->linkInsuranceClaim($validated['insurance_claim_number']);

        return response()->json([
            'message' => 'Insurance claim linked',
            'data' => new TheftAlertResource($theftAlert->fresh()),
        ]);
    }

    /**
     * Get statistics
     *
     * Returns theft alert statistics for the organization.
     *
     * @authenticated
     *
     * @queryParam from_date date Filter statistics from this date. Example: 2025-11-01
     * @queryParam to_date date Filter statistics until this date. Example: 2025-11-16
     *
     * @response 200 {
     *   "data": {
     *     "total_alerts": 125,
     *     "by_status": {
     *       "pending": 12,
     *       "investigating": 8,
     *       "false_alarm": 85,
     *       "confirmed_theft": 5,
     *       "resolved": 15
     *     },
     *     "by_severity": {
     *       "low": 30,
     *       "medium": 50,
     *       "high": 35,
     *       "critical": 10
     *     },
     *     "by_type": {
     *       "movement_outside_hours": 45,
     *       "geofence_violation": 30,
     *       "unauthorized_ignition": 15
     *     },
     *     "average_response_time_minutes": 18,
     *     "average_resolution_time_hours": 6.5,
     *     "confirmed_theft_rate": 4.0
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = TheftAlert::forOrganization($request->user()->organization_id);

        if ($request->filled('from_date')) {
            $query->where('detected_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('detected_at', '<=', $request->to_date);
        }

        $alerts = $query->get();

        $stats = [
            'total_alerts' => $alerts->count(),
            'by_status' => [
                'pending' => $alerts->where('status', 'pending')->count(),
                'investigating' => $alerts->where('status', 'investigating')->count(),
                'false_alarm' => $alerts->where('status', 'false_alarm')->count(),
                'confirmed_theft' => $alerts->where('status', 'confirmed_theft')->count(),
                'resolved' => $alerts->where('status', 'resolved')->count(),
            ],
            'by_severity' => [
                'low' => $alerts->where('severity', 'low')->count(),
                'medium' => $alerts->where('severity', 'medium')->count(),
                'high' => $alerts->where('severity', 'high')->count(),
                'critical' => $alerts->where('severity', 'critical')->count(),
            ],
            'by_type' => $alerts->groupBy('type')->map->count()->toArray(),
            'average_response_time_minutes' => round($alerts->filter(fn($a) => $a->getResponseTimeMinutes())->avg(fn($a) => $a->getResponseTimeMinutes()), 1),
            'average_resolution_time_hours' => round($alerts->filter(fn($a) => $a->getResolutionTimeHours())->avg(fn($a) => $a->getResolutionTimeHours()), 1),
            'confirmed_theft_rate' => $alerts->count() > 0 ? round(($alerts->where('status', 'confirmed_theft')->count() / $alerts->count()) * 100, 1) : 0,
        ];

        return response()->json(['data' => $stats]);
    }
}
