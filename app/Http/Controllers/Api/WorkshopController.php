<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Workshop::with(['organization']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by preferred
        if ($request->has('is_preferred')) {
            $query->where('is_preferred', $request->boolean('is_preferred'));
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Get only preferred workshops
        if ($request->has('preferred_only') && $request->boolean('preferred_only')) {
            $query->preferred();
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('contact_name', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $workshops = $query->orderBy('rating', 'desc')->orderBy('name')->paginate($perPage);

        return response()->json($workshops);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'contact_name' => 'nullable|string|max:255',
            'services_offered' => 'nullable|array',
            'brands_serviced' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_preferred'] = $data['is_preferred'] ?? false;
        $data['rating'] = $data['rating'] ?? 0;
        $data['total_interventions'] = 0;

        $workshop = Workshop::create($data);
        $workshop->load(['organization']);

        return response()->json([
            'message' => 'Workshop created successfully',
            'data' => $workshop
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $workshop = Workshop::with([
            'organization',
            'maintenances' => function ($query) {
                $query->latest()->limit(10);
            }
        ])->findOrFail($id);

        // Add calculated fields
        $workshop->total_maintenances = $workshop->maintenances()->count();
        $workshop->completed_maintenances = $workshop->maintenances()->where('status', 'completed')->count();

        return response()->json([
            'data' => $workshop
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'type' => 'string|max:50',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'contact_name' => 'nullable|string|max:255',
            'services_offered' => 'nullable|array',
            'brands_serviced' => 'nullable|array',
            'rating' => 'nullable|numeric|between:0,5',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workshop->update($request->all());
        $workshop->load(['organization']);

        return response()->json([
            'message' => 'Workshop updated successfully',
            'data' => $workshop
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);

        // Check if workshop has maintenances
        if ($workshop->maintenances()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete workshop with existing maintenances',
                'maintenance_count' => $workshop->maintenances()->count()
            ], 409);
        }

        $workshop->delete();

        return response()->json([
            'message' => 'Workshop deleted successfully'
        ]);
    }

    /**
     * Update workshop rating
     */
    public function updateRating(string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);
        $workshop->updateRating();

        return response()->json([
            'message' => 'Workshop rating updated successfully',
            'data' => $workshop
        ]);
    }
}
