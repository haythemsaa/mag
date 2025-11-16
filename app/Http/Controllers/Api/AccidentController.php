<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccidentRequest;
use App\Http\Requests\UpdateAccidentRequest;
use App\Http\Resources\AccidentResource;
use App\Models\Accident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Accidents
 *
 * Gestion des accidents et sinistres de la flotte
 */
class AccidentController extends Controller
{
    /**
     * List Accidents
     *
     * @queryParam organization_id integer required ID de l'organisation. Example: 1
     * @queryParam status string Filtrer par statut. Example: in_progress
     * @queryParam severity string Filtrer par gravité. Example: severe
     * @queryParam vehicle_id integer Filtrer par véhicule. Example: 5
     * @queryParam driver_id integer Filtrer par conducteur. Example: 3
     * @queryParam per_page integer Items par page. Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Accident::class);

        $query = Accident::with(['vehicle', 'driver'])
            ->forOrganization($request->input('organization_id'));

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        if ($request->has('vehicle_id')) {
            $query->byVehicle($request->vehicle_id);
        }

        if ($request->has('driver_id')) {
            $query->byDriver($request->driver_id);
        }

        if ($request->boolean('with_injuries')) {
            $query->withInjuries();
        }

        if ($request->boolean('in_progress')) {
            $query->inProgress();
        }

        $accidents = $query->latest('accident_date')
            ->paginate($request->input('per_page', 15));

        return AccidentResource::collection($accidents);
    }

    /**
     * Create Accident
     */
    public function store(StoreAccidentRequest $request): JsonResponse
    {
        $accident = Accident::create($request->validated());

        return (new AccidentResource($accident))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show Accident
     */
    public function show(Accident $accident): AccidentResource
    {
        $this->authorize('view', $accident);

        $accident->load(['vehicle', 'driver', 'photos']);

        return new AccidentResource($accident);
    }

    /**
     * Update Accident
     */
    public function update(UpdateAccidentRequest $request, Accident $accident): AccidentResource
    {
        $accident->update($request->validated());

        return new AccidentResource($accident->fresh());
    }

    /**
     * Delete Accident
     */
    public function destroy(Accident $accident): JsonResponse
    {
        $this->authorize('delete', $accident);

        $accident->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark as Expertised
     */
    public function markAsExpertised(Request $request, Accident $accident): AccidentResource
    {
        $this->authorize('update', $accident);

        $request->validate([
            'estimated_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $accident->markAsExpertised(
            $request->estimated_cost,
            $request->notes
        );

        return new AccidentResource($accident->fresh());
    }

    /**
     * Mark as Repaired
     */
    public function markAsRepaired(Request $request, Accident $accident): AccidentResource
    {
        $this->authorize('update', $accident);

        $request->validate([
            'final_cost' => 'required|numeric|min:0',
        ]);

        $accident->markAsRepaired($request->final_cost);

        return new AccidentResource($accident->fresh());
    }

    /**
     * Close Accident
     */
    public function close(Accident $accident): AccidentResource
    {
        $this->authorize('update', $accident);

        $accident->close();

        return new AccidentResource($accident->fresh());
    }

    /**
     * File Insurance Claim
     */
    public function fileInsuranceClaim(Request $request, Accident $accident): AccidentResource
    {
        $this->authorize('update', $accident);

        $request->validate([
            'claim_number' => 'required|string|max:255',
        ]);

        $accident->fileInsuranceClaim($request->claim_number);

        return new AccidentResource($accident->fresh());
    }

    /**
     * Accident Statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');

        $accidents = Accident::forOrganization($organizationId);

        $stats = [
            'total_accidents' => $accidents->count(),
            'with_injuries' => $accidents->withInjuries()->count(),
            'in_progress' => $accidents->inProgress()->count(),
            'total_estimated_cost' => $accidents->sum('estimated_cost'),
            'total_final_cost' => $accidents->sum('final_cost'),

            'by_severity' => $accidents->select('severity')
                ->selectRaw('count(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),

            'by_status' => $accidents->select('status')
                ->selectRaw('count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),

            'by_responsibility' => $accidents->select('responsibility')
                ->selectRaw('count(*) as count')
                ->groupBy('responsibility')
                ->pluck('count', 'responsibility'),

            'top_vehicles' => $accidents->select('vehicle_id')
                ->whereNotNull('vehicle_id')
                ->selectRaw('count(*) as count')
                ->groupBy('vehicle_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get(),

            'top_drivers' => $accidents->select('driver_id')
                ->whereNotNull('driver_id')
                ->selectRaw('count(*) as count')
                ->groupBy('driver_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get(),
        ];

        return response()->json(['data' => $stats]);
    }
}
