<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Driver::with(['organization', 'site', 'vehicles']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by site
        if ($request->has('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%")
                    ->orWhere('license_number', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $drivers = $query->orderBy('last_name')->orderBy('first_name')->paginate($perPage);

        return response()->json($drivers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'site_id' => 'nullable|exists:sites,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:drivers,email',
            'license_number' => 'required|string|unique:drivers,license_number',
            'license_type' => 'required|string|max:10',
            'license_expiry_date' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'phone_mobile' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver = Driver::create($request->all());
        $driver->load(['organization', 'site']);

        return response()->json([
            'message' => 'Driver created successfully',
            'data' => $driver
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $driver = Driver::with([
            'organization',
            'site',
            'vehicles',
            'fuelTransactions' => function ($query) {
                $query->latest()->limit(10);
            },
            'gpsPositions' => function ($query) {
                $query->latest()->limit(10);
            }
        ])->findOrFail($id);

        // Add calculated fields
        $driver->license_expiring_soon = $driver->licenseExpiringSoon();
        $driver->medical_check_due = $driver->medicalCheckDue();
        $driver->total_vehicles = $driver->vehicles()->count();

        return response()->json([
            'data' => $driver
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'site_id' => 'nullable|exists:sites,id',
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'email' => 'email|unique:drivers,email,' . $id,
            'license_number' => 'string|unique:drivers,license_number,' . $id,
            'license_type' => 'string|max:10',
            'license_expiry_date' => 'nullable|date',
            'license_points' => 'integer|min:0|max:12',
            'eco_driving_score' => 'integer|min:0|max:100',
            'status' => 'string|in:active,suspended,terminated',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver->update($request->all());
        $driver->load(['organization', 'site']);

        return response()->json([
            'message' => 'Driver updated successfully',
            'data' => $driver
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        // Check if driver has assigned vehicles
        if ($driver->vehicles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete driver with assigned vehicles',
                'assigned_vehicles_count' => $driver->vehicles()->count()
            ], 409);
        }

        $driver->delete();

        return response()->json([
            'message' => 'Driver deleted successfully'
        ]);
    }
}
