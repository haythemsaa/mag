<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organization::with(['parent', 'children']);

        // Filter by parent organization
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Filter by subscription plan
        if ($request->has('subscription_plan')) {
            $query->where('subscription_plan', $request->subscription_plan);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Get only root organizations (no parent)
        if ($request->has('root_only') && $request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('legal_name', 'LIKE', "%{$search}%")
                    ->orWhere('siret', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $organizations = $query->orderBy('name')->paginate($perPage);

        return response()->json($organizations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'siret' => 'nullable|string|max:14|unique:organizations,siret',
            'vat_number' => 'nullable|string|max:20|unique:organizations,vat_number',
            'email' => 'required|email|unique:organizations,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'parent_id' => 'nullable|exists:organizations,id',
            'subscription_plan' => 'required|in:starter,professional,enterprise',
            'max_vehicles' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['is_active'] = $data['is_active'] ?? true;

        $organization = Organization::create($data);
        $organization->load(['parent']);

        return response()->json([
            'message' => 'Organization created successfully',
            'data' => $organization
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $organization = Organization::with([
            'parent',
            'children',
            'sites',
            'vehicles' => function ($query) {
                $query->limit(10);
            },
            'drivers' => function ($query) {
                $query->limit(10);
            }
        ])->findOrFail($id);

        // Add calculated fields
        $organization->total_vehicles = $organization->vehicles()->count();
        $organization->total_drivers = $organization->drivers()->count();
        $organization->total_sites = $organization->sites()->count();
        $organization->is_at_vehicle_limit = $organization->isAtVehicleLimit();
        $organization->remaining_vehicle_slots = $organization->remainingVehicleSlots();

        return response()->json([
            'data' => $organization
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'siret' => 'nullable|string|max:14|unique:organizations,siret,' . $id,
            'vat_number' => 'nullable|string|max:20|unique:organizations,vat_number,' . $id,
            'email' => 'email|unique:organizations,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'parent_id' => 'nullable|exists:organizations,id',
            'subscription_plan' => 'in:starter,professional,enterprise',
            'max_vehicles' => 'integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if reducing max_vehicles below current count
        if ($request->has('max_vehicles')) {
            $currentVehicleCount = $organization->vehicles()->count();
            if ($request->max_vehicles < $currentVehicleCount) {
                return response()->json([
                    'message' => 'Cannot reduce max_vehicles below current vehicle count',
                    'current_vehicle_count' => $currentVehicleCount,
                    'requested_max_vehicles' => $request->max_vehicles
                ], 409);
            }
        }

        // Prevent circular parent reference
        if ($request->has('parent_id') && $request->parent_id) {
            if ($request->parent_id == $id) {
                return response()->json([
                    'message' => 'An organization cannot be its own parent'
                ], 409);
            }

            // Check if the new parent is a child of this organization
            $newParent = Organization::find($request->parent_id);
            if ($newParent && $newParent->parent_id == $id) {
                return response()->json([
                    'message' => 'Cannot create circular parent-child relationship'
                ], 409);
            }
        }

        $organization->update($request->all());
        $organization->load(['parent', 'children']);

        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => $organization
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);

        // Check if organization has child organizations
        if ($organization->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete organization with child organizations',
                'child_count' => $organization->children()->count()
            ], 409);
        }

        // Check if organization has vehicles
        if ($organization->vehicles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete organization with vehicles',
                'vehicle_count' => $organization->vehicles()->count()
            ], 409);
        }

        // Check if organization has drivers
        if ($organization->drivers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete organization with drivers',
                'driver_count' => $organization->drivers()->count()
            ], 409);
        }

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully'
        ]);
    }

    /**
     * Get organization statistics
     */
    public function statistics(string $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);

        $stats = [
            'vehicles' => [
                'total' => $organization->vehicles()->count(),
                'active' => $organization->vehicles()->where('status', 'active')->count(),
                'available' => $organization->vehicles()->where('status', 'available')->count(),
                'in_maintenance' => $organization->vehicles()->where('status', 'maintenance')->count(),
                'max_allowed' => $organization->max_vehicles,
                'remaining_slots' => $organization->remainingVehicleSlots(),
            ],
            'drivers' => [
                'total' => $organization->drivers()->count(),
                'active' => $organization->drivers()->where('status', 'active')->count(),
                'suspended' => $organization->drivers()->where('status', 'suspended')->count(),
            ],
            'sites' => [
                'total' => $organization->sites()->count(),
            ],
            'maintenances' => [
                'total' => $organization->maintenances()->count(),
                'scheduled' => $organization->maintenances()->where('status', 'scheduled')->count(),
                'in_progress' => $organization->maintenances()->where('status', 'in_progress')->count(),
                'completed' => $organization->maintenances()->where('status', 'completed')->count(),
                'total_cost_this_year' => $organization->maintenances()
                    ->whereYear('completed_date', date('Y'))
                    ->sum('total_cost'),
            ],
            'fuel_transactions' => [
                'total' => $organization->fuelTransactions()->count(),
                'validated' => $organization->fuelTransactions()->where('validated', true)->count(),
                'anomalies' => $organization->fuelTransactions()->where('anomaly_detected', true)->count(),
                'total_cost_this_month' => $organization->fuelTransactions()
                    ->whereMonth('transaction_date', date('m'))
                    ->whereYear('transaction_date', date('Y'))
                    ->sum('total_cost'),
            ],
            'costs' => [
                'total_this_year' => $organization->costs()
                    ->whereYear('date', date('Y'))
                    ->sum('amount'),
                'by_category' => $organization->costs()
                    ->whereYear('date', date('Y'))
                    ->groupBy('category')
                    ->selectRaw('category, SUM(amount) as total')
                    ->pluck('total', 'category'),
            ],
        ];

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get organization dashboard data
     */
    public function dashboard(string $id): JsonResponse
    {
        $organization = Organization::with(['vehicles', 'drivers'])->findOrFail($id);

        // Upcoming maintenances
        $upcomingMaintenances = $organization->maintenances()
            ->with(['vehicle', 'workshop'])
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [now(), now()->addDays(7)])
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();

        // Recent fuel transactions with anomalies
        $recentAnomalies = $organization->fuelTransactions()
            ->with(['vehicle', 'driver'])
            ->where('anomaly_detected', true)
            ->where('validated', false)
            ->orderBy('transaction_date', 'desc')
            ->limit(5)
            ->get();

        // Vehicles needing attention
        $vehiclesNeedingAttention = $organization->vehicles()
            ->where(function ($query) {
                $query->where('technical_control_date', '<', now()->addDays(30))
                    ->orWhereRaw('current_mileage >= next_service_mileage');
            })
            ->limit(5)
            ->get();

        // Drivers with license expiring soon
        $driversLicenseExpiring = $organization->drivers()
            ->where('license_expiry_date', '<=', now()->addDays(30))
            ->where('license_expiry_date', '>=', now())
            ->limit(5)
            ->get();

        $dashboard = [
            'upcoming_maintenances' => $upcomingMaintenances,
            'recent_anomalies' => $recentAnomalies,
            'vehicles_needing_attention' => $vehiclesNeedingAttention,
            'drivers_license_expiring' => $driversLicenseExpiring,
            'quick_stats' => [
                'total_vehicles' => $organization->vehicles()->count(),
                'active_drivers' => $organization->drivers()->where('status', 'active')->count(),
                'pending_maintenances' => $organization->maintenances()->where('status', 'scheduled')->count(),
                'unvalidated_fuel_transactions' => $organization->fuelTransactions()->where('validated', false)->count(),
            ],
        ];

        return response()->json([
            'data' => $dashboard
        ]);
    }
}
