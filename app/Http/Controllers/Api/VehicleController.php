<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with(['organization', 'site', 'currentDriver']);

        // Filter by organization (multi-tenant)
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by site
        if ($request->has('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        // Filter by brand
        if ($request->has('brand')) {
            $query->where('brand', $request->brand);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('registration', 'LIKE', "%{$search}%")
                    ->orWhere('vin', 'LIKE', "%{$search}%")
                    ->orWhere('brand', 'LIKE', "%{$search}%")
                    ->orWhere('model', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $vehicles = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($vehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'site_id' => 'nullable|exists:sites,id',
            'vin' => 'required|string|unique:vehicles,vin',
            'registration' => 'required|string|unique:vehicles,registration',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'acquisition_mode' => 'required|in:purchase,lease,rental',
            'fuel_type' => 'required|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle = Vehicle::create($request->all());
        $vehicle->load(['organization', 'site']);

        return response()->json([
            'message' => 'Vehicle created successfully',
            'data' => $vehicle
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $vehicle = Vehicle::with([
            'organization',
            'site',
            'currentDriver',
            'contracts',
            'maintenances' => function ($query) {
                $query->latest()->limit(5);
            },
            'fuelTransactions' => function ($query) {
                $query->latest()->limit(10);
            },
            'costs' => function ($query) {
                $query->latest()->limit(10);
            }
        ])->findOrFail($id);

        // Add calculated fields
        $vehicle->average_consumption = $vehicle->calculateAverageConsumption();
        $vehicle->total_tco = $vehicle->calculateTCO();
        $vehicle->needs_maintenance = $vehicle->needsMaintenance();

        return response()->json([
            'data' => $vehicle
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'site_id' => 'nullable|exists:sites,id',
            'registration' => 'string|unique:vehicles,registration,' . $id,
            'brand' => 'string|max:255',
            'model' => 'string|max:255',
            'current_mileage' => 'integer|min:0',
            'status' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle->update($request->all());
        $vehicle->load(['organization', 'site', 'currentDriver']);

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully'
        ]);
    }

    /**
     * Get vehicle statistics
     */
    public function statistics(string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $stats = [
            'total_distance' => $vehicle->current_mileage - $vehicle->initial_mileage,
            'average_consumption' => $vehicle->calculateAverageConsumption(),
            'total_cost' => $vehicle->calculateTCO(),
            'maintenance_count' => $vehicle->maintenances()->count(),
            'fuel_transactions_count' => $vehicle->fuelTransactions()->count(),
            'total_fuel_cost' => $vehicle->fuelTransactions()->sum('total_cost'),
            'total_maintenance_cost' => $vehicle->maintenances()->sum('total_cost'),
            'days_since_acquisition' => $vehicle->acquisition_date
                ? $vehicle->acquisition_date->diffInDays(now())
                : null,
        ];

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Assign driver to vehicle
     */
    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle->current_driver_id = $request->driver_id;
        $vehicle->status = 'assigned';
        $vehicle->save();

        $vehicle->load('currentDriver');

        return response()->json([
            'message' => 'Driver assigned successfully',
            'data' => $vehicle
        ]);
    }

    /**
     * Unassign driver from vehicle
     */
    public function unassignDriver(string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $vehicle->current_driver_id = null;
        $vehicle->status = 'available';
        $vehicle->save();

        return response()->json([
            'message' => 'Driver unassigned successfully',
            'data' => $vehicle
        ]);
    }

    /**
     * Update vehicle mileage
     */
    public function updateMileage(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'mileage' => 'required|integer|min:' . $vehicle->current_mileage,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $vehicle->current_mileage = $request->mileage;
        $vehicle->last_mileage_update = now();
        $vehicle->save();

        return response()->json([
            'message' => 'Mileage updated successfully',
            'data' => $vehicle
        ]);
    }
}
