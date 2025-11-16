<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashcamEventResource;
use App\Models\DashcamEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Dashcam Events
 *
 * APIs for managing dashcam events
 */
class DashcamEventController extends Controller
{
    /**
     * List dashcam events
     *
     * Get a paginated list of dashcam events for the authenticated user's organization.
     *
     * @queryParam event_type string Filter by event type. Example: harsh_braking
     * @queryParam severity string Filter by severity (low, medium, high, critical). Example: high
     * @queryParam status string Filter by status. Example: pending_review
     * @queryParam provider string Filter by dashcam provider. Example: lytx
     * @queryParam vehicle_id int Filter by vehicle ID. Example: 1
     * @queryParam driver_id int Filter by driver ID. Example: 1
     * @queryParam start_date date Filter events from this date. Example: 2025-01-01
     * @queryParam end_date date Filter events until this date. Example: 2025-12-31
     * @queryParam disputed boolean Filter disputed events. Example: true
     * @queryParam coaching_required boolean Filter events requiring coaching. Example: true
     * @queryParam per_page int Number of items per page (default: 15). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = DashcamEvent::where('organization_id', $request->user()->organization_id)
            ->with(['vehicle', 'driver', 'reviewer', 'coach'])
            ->latest('event_timestamp');

        // Apply filters
        if ($request->has('event_type')) {
            $query->ofType($request->event_type);
        }

        if ($request->has('severity')) {
            $query->withSeverity($request->severity);
        }

        if ($request->has('status')) {
            $query->withStatus($request->status);
        }

        if ($request->has('provider')) {
            $query->byProvider($request->provider);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        }

        if ($request->boolean('disputed')) {
            $query->disputed();
        }

        if ($request->boolean('coaching_required')) {
            $query->requiringCoaching();
        }

        $perPage = $request->input('per_page', 15);
        $events = $query->paginate($perPage);

        return response()->json([
            'data' => DashcamEventResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'total' => $events->total(),
                'per_page' => $events->perPage(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    /**
     * Get event details
     *
     * Retrieve detailed information about a specific dashcam event.
     *
     * @urlParam event int required The event ID. Example: 1
     */
    public function show(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'data' => new DashcamEventResource($event->load(['vehicle', 'driver', 'reviewer', 'coach'])),
        ]);
    }

    /**
     * Mark event as reviewed
     *
     * Review a dashcam event and optionally add notes.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam notes string Review notes. Example: Driver was avoiding obstacle
     */
    public function review(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $event->markAsReviewed($request->user()->id, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Event reviewed successfully',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Acknowledge event
     *
     * Mark an event as acknowledged.
     *
     * @urlParam event int required The event ID. Example: 1
     */
    public function acknowledge(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event->acknowledge();

        return response()->json([
            'message' => 'Event acknowledged successfully',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Dismiss event
     *
     * Dismiss an event with a reason.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam reason string required Reason for dismissal. Example: False positive - camera malfunction
     */
    public function dismiss(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $event->dismiss($request->user()->id, $validated['reason']);

        return response()->json([
            'message' => 'Event dismissed successfully',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Require coaching
     *
     * Mark an event as requiring driver coaching.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam notes string Notes about why coaching is required. Example: Repeated harsh braking behavior
     */
    public function requireCoaching(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $event->requireCoaching($request->user()->id, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Coaching required for this event',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Complete coaching
     *
     * Mark coaching as completed for an event.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam notes string Coaching session notes. Example: Reviewed safe driving practices with driver
     */
    public function completeCoaching(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $event->completeCoaching($request->user()->id, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Coaching completed successfully',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Dispute event
     *
     * Dispute a dashcam event with a reason.
     *
     * @urlParam event int required The event ID. Example: 1
     * @bodyParam reason string required Reason for disputing. Example: Emergency maneuver to avoid collision
     */
    public function dispute(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $event->dispute($validated['reason']);

        return response()->json([
            'message' => 'Event disputed successfully',
            'data' => new DashcamEventResource($event->fresh()),
        ]);
    }

    /**
     * Delete event
     *
     * Soft delete a dashcam event.
     *
     * @urlParam event int required The event ID. Example: 1
     */
    public function destroy(Request $request, DashcamEvent $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    /**
     * Get event statistics
     *
     * Get statistics about dashcam events for the organization.
     *
     * @queryParam start_date date Filter statistics from this date. Example: 2025-01-01
     * @queryParam end_date date Filter statistics until this date. Example: 2025-12-31
     * @queryParam vehicle_id int Filter by vehicle ID. Example: 1
     * @queryParam driver_id int Filter by driver ID. Example: 1
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = DashcamEvent::where('organization_id', $request->user()->organization_id);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        $events = $query->get();

        $statistics = [
            'total_events' => $events->count(),
            'by_type' => [
                'harsh_braking' => $events->where('event_type', 'harsh_braking')->count(),
                'harsh_acceleration' => $events->where('event_type', 'harsh_acceleration')->count(),
                'harsh_cornering' => $events->where('event_type', 'harsh_cornering')->count(),
                'collision' => $events->where('event_type', 'collision')->count(),
                'speeding' => $events->where('event_type', 'speeding')->count(),
                'distraction' => $events->where('event_type', 'distraction')->count(),
                'phone_usage' => $events->where('event_type', 'phone_usage')->count(),
                'other' => $events->where('event_type', 'other')->count(),
            ],
            'by_severity' => [
                'critical' => $events->where('severity', 'critical')->count(),
                'high' => $events->where('severity', 'high')->count(),
                'medium' => $events->where('severity', 'medium')->count(),
                'low' => $events->where('severity', 'low')->count(),
            ],
            'by_status' => [
                'pending_review' => $events->where('status', 'pending_review')->count(),
                'reviewed' => $events->where('status', 'reviewed')->count(),
                'acknowledged' => $events->where('status', 'acknowledged')->count(),
                'disputed' => $events->where('status', 'disputed')->count(),
                'dismissed' => $events->where('status', 'dismissed')->count(),
                'coaching_required' => $events->where('status', 'coaching_required')->count(),
            ],
            'by_provider' => $events->groupBy('dashcam_provider')
                ->map(fn ($group) => $group->count())
                ->toArray(),
            'disputed_count' => $events->where('is_disputed', true)->count(),
            'coaching_required_count' => $events->where('status', 'coaching_required')
                ->where('coaching_completed', false)
                ->count(),
            'coaching_completed_count' => $events->where('coaching_completed', true)->count(),
            'events_with_video' => $events->filter(fn ($event) => $event->hasVideo())->count(),
            'average_max_g_force' => round($events->whereNotNull('max_g_force')->avg('max_g_force'), 2),
            'speeding_events' => $events->filter(fn ($event) => $event->isSpeedingEvent())->count(),
            'critical_g_force_events' => $events->filter(fn ($event) => $event->isCriticalGForce())->count(),
            'top_vehicles' => $events->groupBy('vehicle_id')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->take(5)
                ->toArray(),
            'top_drivers' => $events->whereNotNull('driver_id')
                ->groupBy('driver_id')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->take(5)
                ->toArray(),
        ];

        return response()->json($statistics);
    }
}
