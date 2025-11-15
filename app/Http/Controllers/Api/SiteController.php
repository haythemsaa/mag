<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Site::with(['organization']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $sites = $query->orderBy('name')->paginate($perPage);

        return response()->json($sites);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:sites,code',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['is_active'] = $data['is_active'] ?? true;

        $site = Site::create($data);
        $site->load(['organization']);

        return response()->json([
            'message' => 'Site created successfully',
            'data' => $site
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $site = Site::with([
            'organization',
            'vehicles' => function ($query) {
                $query->limit(10);
            },
            'drivers' => function ($query) {
                $query->limit(10);
            }
        ])->findOrFail($id);

        // Add calculated fields
        $site->total_vehicles = $site->vehicles()->count();
        $site->total_drivers = $site->drivers()->count();
        $site->active_vehicles_count = $site->activeVehiclesCount();

        return response()->json([
            'data' => $site
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'code' => 'nullable|string|max:50|unique:sites,code,' . $id,
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $site->update($request->all());
        $site->load(['organization']);

        return response()->json([
            'message' => 'Site updated successfully',
            'data' => $site
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        // Check if site has vehicles
        if ($site->vehicles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete site with assigned vehicles',
                'vehicle_count' => $site->vehicles()->count()
            ], 409);
        }

        // Check if site has drivers
        if ($site->drivers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete site with assigned drivers',
                'driver_count' => $site->drivers()->count()
            ], 409);
        }

        $site->delete();

        return response()->json([
            'message' => 'Site deleted successfully'
        ]);
    }
}
