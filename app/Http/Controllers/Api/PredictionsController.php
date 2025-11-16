<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenancePredictionResource;
use App\Models\MaintenancePrediction;
use App\Models\Vehicle;
use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Predictions
 *
 * APIs for predictive maintenance and analytics
 */
class PredictionsController extends Controller
{
    public function __construct(protected PredictionService $predictionService)
    {
    }

    /**
     * Get predictions
     *
     * Get predictions for the organization with optional filters.
     *
     * @queryParam vehicle_id int Filter by vehicle ID. Example: 1
     * @queryParam prediction_type string Filter by type. Example: maintenance_due
     * @queryParam status string Filter by status. Example: pending
     * @queryParam priority string Filter by priority. Example: high
     * @queryParam confidence string Filter by confidence level. Example: high
     * @queryParam overdue boolean Show only overdue predictions. Example: true
     * @queryParam due_soon boolean Show predictions due within 7 days. Example: true
     * @queryParam per_page int Number of items per page (default: 15). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = MaintenancePrediction::where('organization_id', $request->user()->organization_id)
            ->with(['vehicle', 'acknowledgedBy', 'dismissedBy', 'relatedMaintenance', 'relatedContract'])
            ->latest('created_at');

        // Filters
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->has('prediction_type')) {
            $query->where('prediction_type', $request->prediction_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('confidence')) {
            $query->where('confidence', $request->confidence);
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($request->boolean('due_soon')) {
            $query->dueSoon($request->input('due_soon_days', 7));
        }

        $perPage = $request->input('per_page', 15);
        $predictions = $query->paginate($perPage);

        return response()->json([
            'data' => MaintenancePredictionResource::collection($predictions->items()),
            'meta' => [
                'current_page' => $predictions->currentPage(),
                'total' => $predictions->total(),
                'per_page' => $predictions->perPage(),
                'last_page' => $predictions->lastPage(),
            ],
        ]);
    }

    /**
     * Get prediction
     *
     * Get a specific prediction by ID.
     *
     * @urlParam prediction int required The prediction ID. Example: 1
     */
    public function show(Request $request, MaintenancePrediction $prediction): JsonResponse
    {
        if ($prediction->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $prediction->load(['vehicle', 'acknowledgedBy', 'dismissedBy', 'relatedMaintenance', 'relatedContract', 'scheduledMaintenance']);

        return response()->json([
            'data' => new MaintenancePredictionResource($prediction),
        ]);
    }

    /**
     * Generate predictions for vehicle
     *
     * Generate new predictions for a specific vehicle based on historical data.
     *
     * @urlParam vehicle int required The vehicle ID. Example: 1
     */
    public function generate(Request $request, Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $predictions = $this->predictionService->generatePredictionsForVehicle($vehicle);

        return response()->json([
            'message' => 'Predictions generated successfully',
            'data' => MaintenancePredictionResource::collection($predictions),
            'count' => $predictions->count(),
        ]);
    }

    /**
     * Generate predictions for all vehicles
     *
     * Generate predictions for all active vehicles in the organization.
     */
    public function generateAll(Request $request): JsonResponse
    {
        $vehicles = Vehicle::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get();

        $allPredictions = collect();

        foreach ($vehicles as $vehicle) {
            $predictions = $this->predictionService->generatePredictionsForVehicle($vehicle);
            $allPredictions = $allPredictions->merge($predictions);
        }

        return response()->json([
            'message' => 'Predictions generated for all vehicles',
            'vehicles_processed' => $vehicles->count(),
            'predictions_created' => $allPredictions->count(),
        ]);
    }

    /**
     * Acknowledge prediction
     *
     * Mark a prediction as acknowledged.
     *
     * @urlParam prediction int required The prediction ID. Example: 1
     * @bodyParam notes string Optional acknowledgment notes. Example: Will schedule maintenance next week
     */
    public function acknowledge(Request $request, MaintenancePrediction $prediction): JsonResponse
    {
        if ($prediction->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $prediction->acknowledge($request->user(), $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Prediction acknowledged successfully',
            'data' => new MaintenancePredictionResource($prediction->fresh()),
        ]);
    }

    /**
     * Dismiss prediction
     *
     * Dismiss a prediction with a reason.
     *
     * @urlParam prediction int required The prediction ID. Example: 1
     * @bodyParam reason string required Reason for dismissal. Example: Already addressed
     */
    public function dismiss(Request $request, MaintenancePrediction $prediction): JsonResponse
    {
        if ($prediction->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $prediction->dismiss($request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Prediction dismissed successfully',
            'data' => new MaintenancePredictionResource($prediction->fresh()),
        ]);
    }

    /**
     * Delete prediction
     *
     * Delete a prediction record.
     *
     * @urlParam prediction int required The prediction ID. Example: 1
     */
    public function destroy(Request $request, MaintenancePrediction $prediction): JsonResponse
    {
        if ($prediction->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $prediction->delete();

        return response()->json([
            'message' => 'Prediction deleted successfully',
        ]);
    }

    /**
     * Get prediction statistics
     *
     * Get prediction statistics for the organization.
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->predictionService->getOrganizationStatistics($request->user()->organization_id);

        return response()->json($stats);
    }

    /**
     * Get predictions by vehicle
     *
     * Get all predictions for a specific vehicle.
     *
     * @urlParam vehicle int required The vehicle ID. Example: 1
     */
    public function byVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $predictions = MaintenancePrediction::where('vehicle_id', $vehicle->id)
            ->with(['acknowledgedBy', 'dismissedBy', 'relatedMaintenance', 'relatedContract'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => MaintenancePredictionResource::collection($predictions),
            'vehicle' => [
                'id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
            ],
        ]);
    }

    /**
     * Get overdue predictions
     *
     * Get all overdue predictions for the organization.
     */
    public function overdue(Request $request): JsonResponse
    {
        $predictions = MaintenancePrediction::where('organization_id', $request->user()->organization_id)
            ->overdue()
            ->with(['vehicle', 'acknowledgedBy'])
            ->orderBy('predicted_date')
            ->get();

        return response()->json([
            'data' => MaintenancePredictionResource::collection($predictions),
            'count' => $predictions->count(),
        ]);
    }

    /**
     * Get due soon predictions
     *
     * Get predictions due within specified days.
     *
     * @queryParam days int Number of days to look ahead (default: 7). Example: 14
     */
    public function dueSoon(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        $predictions = MaintenancePrediction::where('organization_id', $request->user()->organization_id)
            ->dueSoon($days)
            ->with(['vehicle', 'acknowledgedBy'])
            ->orderBy('predicted_date')
            ->get();

        return response()->json([
            'data' => MaintenancePredictionResource::collection($predictions),
            'count' => $predictions->count(),
            'days_ahead' => $days,
        ]);
    }

    /**
     * Get critical predictions
     *
     * Get all critical priority predictions.
     */
    public function critical(Request $request): JsonResponse
    {
        $predictions = MaintenancePrediction::where('organization_id', $request->user()->organization_id)
            ->critical()
            ->whereNotIn('status', ['completed', 'dismissed'])
            ->with(['vehicle', 'acknowledgedBy'])
            ->orderBy('predicted_date')
            ->get();

        return response()->json([
            'data' => MaintenancePredictionResource::collection($predictions),
            'count' => $predictions->count(),
        ]);
    }
}
