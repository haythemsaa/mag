<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInfractionRequest;
use App\Http\Requests\UpdateInfractionRequest;
use App\Http\Resources\InfractionResource;
use App\Models\Infraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Infractions
 *
 * Gestion des infractions et amendes de la flotte
 */
class InfractionController extends Controller
{
    /**
     * List Infractions
     *
     * Récupère la liste des infractions de l'organisation.
     *
     * @queryParam organization_id integer required ID de l'organisation. Example: 1
     * @queryParam status string Filtrer par statut. Example: unpaid
     * @queryParam vehicle_id integer Filtrer par véhicule. Example: 5
     * @queryParam driver_id integer Filtrer par conducteur. Example: 3
     * @queryParam type string Filtrer par type. Example: speeding
     * @queryParam per_page integer Items par page. Example: 15
     *
     * @response 200 {
     *   "data": [{
     *     "id": 1,
     *     "infraction_number": "INF-2025-000001",
     *     "vehicle": {"id": 5, "registration_number": "AB-123-CD"},
     *     "driver": {"id": 3, "full_name": "Jean Dupont"},
     *     "type": "speeding",
     *     "infraction_date": "2025-01-15",
     *     "location": "A6 Lyon",
     *     "amount": 135.00,
     *     "amount_to_pay": 90.00,
     *     "points_deducted": 1,
     *     "status": "received",
     *     "is_overdue": false,
     *     "days_until_due": 12
     *   }],
     *   "meta": {"current_page": 1, "total": 25}
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Infraction::class);

        $query = Infraction::with(['vehicle', 'driver'])
            ->forOrganization($request->input('organization_id'));

        if ($request->has('status')) {
            if ($request->status === 'unpaid') {
                $query->unpaid();
            } elseif ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('vehicle_id')) {
            $query->byVehicle($request->vehicle_id);
        }

        if ($request->has('driver_id')) {
            $query->byDriver($request->driver_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $infractions = $query->latest('infraction_date')
            ->paginate($request->input('per_page', 15));

        return InfractionResource::collection($infractions);
    }

    /**
     * Create Infraction
     *
     * @bodyParam vehicle_id integer required ID du véhicule. Example: 5
     * @bodyParam driver_id integer ID du conducteur. Example: 3
     * @bodyParam type string required Type d'infraction. Example: speeding
     * @bodyParam infraction_date date required Date de l'infraction. Example: 2025-01-15
     * @bodyParam location string required Lieu. Example: A6 Lyon
     * @bodyParam amount number required Montant. Example: 135.00
     * @bodyParam points_deducted integer Points retirés. Example: 1
     *
     * @response 201 {"data": {"id": 1, "infraction_number": "INF-2025-000001"}}
     */
    public function store(StoreInfractionRequest $request): JsonResponse
    {
        $infraction = Infraction::create($request->validated());

        return (new InfractionResource($infraction))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 200 {"data": {"id": 1, "infraction_number": "INF-2025-000001"}}
     */
    public function show(Infraction $infraction): InfractionResource
    {
        $this->authorize('view', $infraction);

        $infraction->load(['vehicle', 'driver', 'attachments']);

        return new InfractionResource($infraction);
    }

    /**
     * Update Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 200 {"data": {"id": 1, "status": "paid"}}
     */
    public function update(UpdateInfractionRequest $request, Infraction $infraction): InfractionResource
    {
        $infraction->update($request->validated());

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Delete Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     *
     * @response 204
     */
    public function destroy(Infraction $infraction): JsonResponse
    {
        $this->authorize('delete', $infraction);

        $infraction->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark as Paid
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     * @bodyParam payment_method string required Mode de paiement. Example: bank_transfer
     * @bodyParam payment_reference string Référence. Example: PAY-12345
     *
     * @response 200 {"data": {"id": 1, "status": "paid"}}
     */
    public function markAsPaid(Request $request, Infraction $infraction): InfractionResource
    {
        $this->authorize('update', $infraction);

        $request->validate([
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        $infraction->markAsPaid(
            $request->payment_method,
            $request->payment_reference
        );

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Contest Infraction
     *
     * @urlParam id integer required ID de l'infraction. Example: 1
     * @bodyParam notes string Notes de contestation. Example: Erreur de plaque
     *
     * @response 200 {"data": {"id": 1, "status": "contested"}}
     */
    public function contest(Request $request, Infraction $infraction): InfractionResource
    {
        $this->authorize('update', $infraction);

        $infraction->contest($request->input('notes'));

        return new InfractionResource($infraction->fresh());
    }

    /**
     * Infraction Statistics
     *
     * @queryParam organization_id integer required ID organisation. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "total_infractions": 25,
     *     "total_amount": 3250.50,
     *     "unpaid_count": 12,
     *     "unpaid_amount": 1500.00,
     *     "by_type": {"speeding": 15, "parking": 8, "red_light": 2},
     *     "by_status": {"paid": 13, "unpaid": 12},
     *     "top_vehicles": [{"vehicle_id": 5, "count": 8}],
     *     "top_drivers": [{"driver_id": 3, "count": 6, "points_lost": 4}]
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');

        $infractions = Infraction::forOrganization($organizationId);

        $stats = [
            'total_infractions' => $infractions->count(),
            'total_amount' => $infractions->sum('amount'),
            'unpaid_count' => $infractions->unpaid()->count(),
            'unpaid_amount' => $infractions->unpaid()->sum('amount'),
            'overdue_count' => $infractions->overdue()->count(),
            'total_points_lost' => $infractions->sum('points_deducted'),

            'by_type' => $infractions->select('type')
                ->selectRaw('count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),

            'by_status' => $infractions->select('status')
                ->selectRaw('count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),

            'top_vehicles' => $infractions->select('vehicle_id')
                ->selectRaw('count(*) as count, sum(amount) as total_amount')
                ->whereNotNull('vehicle_id')
                ->groupBy('vehicle_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get(),

            'top_drivers' => $infractions->select('driver_id')
                ->whereNotNull('driver_id')
                ->selectRaw('count(*) as count, sum(amount) as total_amount, sum(points_deducted) as points_lost')
                ->groupBy('driver_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get(),
        ];

        return response()->json(['data' => $stats]);
    }
}
