<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cost::with(['organization', 'vehicle', 'validator']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by validated status
        if ($request->has('validated')) {
            $query->where('validated', $request->boolean('validated'));
        }

        // Filter by VAT deductible
        if ($request->has('vat_deductible')) {
            $query->where('vat_deductible', $request->boolean('vat_deductible'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'LIKE', "%{$search}%")
                    ->orWhere('supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $costs = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json($costs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'category' => 'required|in:fuel,maintenance,insurance,tax,parking,toll,fine,other',
            'subcategory' => 'nullable|string|max:255',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'mileage' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['validated'] = $data['validated'] ?? false;
        $data['vat_deductible'] = $data['vat_deductible'] ?? true;
        $data['currency'] = $data['currency'] ?? 'EUR';

        $cost = Cost::create($data);
        $cost->load(['organization', 'vehicle']);

        return response()->json([
            'message' => 'Cost created successfully',
            'data' => $cost
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $cost = Cost::with([
            'organization',
            'vehicle',
            'validator'
        ])->findOrFail($id);

        // Add calculated fields
        $cost->cost_per_km = $cost->costPerKm();

        return response()->json([
            'data' => $cost
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cost = Cost::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category' => 'in:fuel,maintenance,insurance,tax,parking,toll,fine,other',
            'subcategory' => 'nullable|string|max:255',
            'date' => 'date',
            'amount' => 'numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'mileage' => 'nullable|integer|min:0',
            'vat_deductible' => 'boolean',
            'vat_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cost->update($request->all());
        $cost->load(['organization', 'vehicle']);

        return response()->json([
            'message' => 'Cost updated successfully',
            'data' => $cost
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $cost = Cost::findOrFail($id);

        // Only allow deletion of non-validated costs
        if ($cost->validated) {
            return response()->json([
                'message' => 'Cannot delete validated cost'
            ], 409);
        }

        $cost->delete();

        return response()->json([
            'message' => 'Cost deleted successfully'
        ]);
    }

    /**
     * Validate a cost
     */
    public function validate(Request $request, string $id): JsonResponse
    {
        $cost = Cost::findOrFail($id);

        $cost->validated = true;
        $cost->validated_by = $request->user()->id ?? null;
        $cost->validated_at = now();
        $cost->save();

        return response()->json([
            'message' => 'Cost validated successfully',
            'data' => $cost
        ]);
    }

    /**
     * Get costs statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');
        $vehicleId = $request->get('vehicle_id');
        $startDate = $request->get('start_date', now()->startOfYear());
        $endDate = $request->get('end_date', now());

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        $query = Cost::where('organization_id', $organizationId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $costs = $query->get();

        $stats = [
            'total_costs' => $costs->sum('amount'),
            'total_count' => $costs->count(),
            'validated_count' => $costs->where('validated', true)->count(),
            'vat_deductible_total' => $costs->where('vat_deductible', true)->sum('vat_amount'),
            'by_category' => $costs->groupBy('category')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                    'average' => $group->avg('amount'),
                ];
            }),
            'monthly_trend' => $costs->groupBy(function ($cost) {
                return $cost->date->format('Y-m');
            })->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            })->sortKeys(),
        ];

        return response()->json([
            'data' => $stats,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }
}
