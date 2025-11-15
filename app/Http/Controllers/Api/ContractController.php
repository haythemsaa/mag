<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['organization', 'vehicle']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by auto_renewal
        if ($request->has('auto_renewal')) {
            $query->where('auto_renewal', $request->boolean('auto_renewal'));
        }

        // Get expiring contracts
        if ($request->has('expiring_soon')) {
            $days = $request->get('expiring_days', 30);
            $query->where('status', 'active')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays($days));
        }

        // Get expired contracts
        if ($request->has('expired') && $request->boolean('expired')) {
            $query->where('end_date', '<', now());
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'LIKE', "%{$search}%")
                    ->orWhere('supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('type', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $contracts = $query->orderBy('end_date', 'desc')->paginate($perPage);

        return response()->json($contracts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'contract_number' => 'required|string|unique:contracts,contract_number',
            'type' => 'required|in:lease,insurance,maintenance,rental',
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'nullable|string|max:255',
            'supplier_email' => 'nullable|email|max:255',
            'supplier_phone' => 'nullable|string|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'monthly_cost' => 'nullable|numeric|min:0',
            'mileage_limit_annual' => 'nullable|integer|min:0',
            'excess_mileage_cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['status'] = $data['status'] ?? 'active';
        $data['auto_renewal'] = $data['auto_renewal'] ?? false;

        // Calculate duration and total cost
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);
            $data['duration_months'] = $startDate->diffInMonths($endDate);

            if (isset($data['monthly_cost'])) {
                $data['total_cost'] = $data['monthly_cost'] * $data['duration_months'];
            }
        }

        $contract = Contract::create($data);
        $contract->load(['organization', 'vehicle']);

        return response()->json([
            'message' => 'Contract created successfully',
            'data' => $contract
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $contract = Contract::with([
            'organization',
            'vehicle'
        ])->findOrFail($id);

        // Add calculated fields
        $contract->is_expiring_soon = $contract->isExpiringSoon();
        $contract->is_expired = $contract->isExpired();
        $contract->remaining_months = $contract->remainingMonths();

        return response()->json([
            'data' => $contract
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'contract_number' => 'string|unique:contracts,contract_number,' . $id,
            'supplier_name' => 'string|max:255',
            'supplier_contact' => 'nullable|string|max:255',
            'supplier_email' => 'nullable|email|max:255',
            'supplier_phone' => 'nullable|string|max:20',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'monthly_cost' => 'nullable|numeric|min:0',
            'mileage_limit_annual' => 'nullable|integer|min:0',
            'excess_mileage_cost' => 'nullable|numeric|min:0',
            'status' => 'in:active,expired,cancelled',
            'auto_renewal' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Recalculate duration and total cost if dates changed
        if (isset($data['start_date']) || isset($data['end_date']) || isset($data['monthly_cost'])) {
            $startDate = \Carbon\Carbon::parse($data['start_date'] ?? $contract->start_date);
            $endDate = \Carbon\Carbon::parse($data['end_date'] ?? $contract->end_date);
            $data['duration_months'] = $startDate->diffInMonths($endDate);

            $monthlyCost = $data['monthly_cost'] ?? $contract->monthly_cost;
            if ($monthlyCost) {
                $data['total_cost'] = $monthlyCost * $data['duration_months'];
            }
        }

        $contract->update($data);
        $contract->load(['organization', 'vehicle']);

        return response()->json([
            'message' => 'Contract updated successfully',
            'data' => $contract
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        $contract->delete();

        return response()->json([
            'message' => 'Contract deleted successfully'
        ]);
    }

    /**
     * Get expiring contracts
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $organizationId = $request->get('organization_id');

        $query = Contract::with(['vehicle'])
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays($days));

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $contracts = $query->orderBy('end_date')->get();

        return response()->json([
            'data' => $contracts,
            'total' => $contracts->count()
        ]);
    }
}
