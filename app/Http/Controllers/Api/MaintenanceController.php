<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Maintenance::with(['organization', 'vehicle', 'workshop', 'driver']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by workshop
        if ($request->has('workshop_id')) {
            $query->where('workshop_id', $request->workshop_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('scheduled_date', [$request->start_date, $request->end_date]);
        }

        // Get upcoming maintenances
        if ($request->has('upcoming')) {
            $days = $request->get('upcoming_days', 7);
            $query->upcoming($days);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $maintenances = $query->orderBy('scheduled_date', 'desc')->paginate($perPage);

        return response()->json($maintenances);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'workshop_id' => 'nullable|exists:workshops,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'type' => 'required|in:preventive,curative,recall',
            'category' => 'nullable|string|max:255',
            'scheduled_date' => 'required|date',
            'description' => 'required|string',
            'mileage_at_service' => 'nullable|integer|min:0',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Generate reference number if not provided
        if (!isset($data['reference_number'])) {
            $data['reference_number'] = 'MAINT-' . date('YmdHis') . '-' . rand(1000, 9999);
        }

        // Set default status
        if (!isset($data['status'])) {
            $data['status'] = 'scheduled';
        }

        $maintenance = Maintenance::create($data);
        $maintenance->load(['organization', 'vehicle', 'workshop', 'driver']);

        return response()->json([
            'message' => 'Maintenance created successfully',
            'data' => $maintenance
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $maintenance = Maintenance::with([
            'organization',
            'vehicle',
            'workshop',
            'driver'
        ])->findOrFail($id);

        // Add calculated fields
        $maintenance->is_overdue = $maintenance->isOverdue();
        $maintenance->calculated_total_cost = $maintenance->calculateTotalCost();

        return response()->json([
            'data' => $maintenance
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'workshop_id' => 'nullable|exists:workshops,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'type' => 'in:preventive,curative,recall',
            'scheduled_date' => 'date',
            'completed_date' => 'nullable|date',
            'mileage_at_service' => 'nullable|integer|min:0',
            'next_service_mileage' => 'nullable|integer|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0',
            'status' => 'in:scheduled,in_progress,completed,cancelled',
            'downtime_hours' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Calculate total cost if labor or parts cost is provided
        if (isset($data['labor_cost']) || isset($data['parts_cost'])) {
            $laborCost = $data['labor_cost'] ?? $maintenance->labor_cost ?? 0;
            $partsCost = $data['parts_cost'] ?? $maintenance->parts_cost ?? 0;
            $data['total_cost'] = $laborCost + $partsCost;
        }

        $maintenance->update($data);
        $maintenance->load(['organization', 'vehicle', 'workshop', 'driver']);

        return response()->json([
            'message' => 'Maintenance updated successfully',
            'data' => $maintenance
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);

        // Only allow deletion of scheduled or cancelled maintenances
        if (in_array($maintenance->status, ['completed', 'in_progress'])) {
            return response()->json([
                'message' => 'Cannot delete maintenance with status: ' . $maintenance->status,
                'current_status' => $maintenance->status
            ], 409);
        }

        $maintenance->delete();

        return response()->json([
            'message' => 'Maintenance deleted successfully'
        ]);
    }

    /**
     * Complete a maintenance
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'completed_date' => 'required|date',
            'mileage_at_service' => 'required|integer|min:0',
            'labor_cost' => 'required|numeric|min:0',
            'parts_cost' => 'required|numeric|min:0',
            'work_done' => 'required|string',
            'parts_replaced' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'next_service_mileage' => 'nullable|integer|min:0',
            'next_service_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['status'] = 'completed';
        $data['total_cost'] = $data['labor_cost'] + $data['parts_cost'];

        // Calculate downtime if scheduled date exists
        if ($maintenance->scheduled_date && isset($data['completed_date'])) {
            $scheduledDate = \Carbon\Carbon::parse($maintenance->scheduled_date);
            $completedDate = \Carbon\Carbon::parse($data['completed_date']);
            $data['downtime_hours'] = $scheduledDate->diffInHours($completedDate);
        }

        $maintenance->update($data);
        $maintenance->load(['organization', 'vehicle', 'workshop', 'driver']);

        // Update workshop rating if workshop exists
        if ($maintenance->workshop) {
            $maintenance->workshop->updateRating();
        }

        return response()->json([
            'message' => 'Maintenance completed successfully',
            'data' => $maintenance
        ]);
    }

    /**
     * Get upcoming maintenances
     */
    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $organizationId = $request->get('organization_id');

        $query = Maintenance::with(['vehicle', 'workshop'])
            ->upcoming($days);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $maintenances = $query->get();

        return response()->json([
            'data' => $maintenances,
            'total' => $maintenances->count()
        ]);
    }

    /**
     * Get overdue maintenances
     */
    public function overdue(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');

        $query = Maintenance::with(['vehicle', 'workshop'])
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now());

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $maintenances = $query->get();

        return response()->json([
            'data' => $maintenances,
            'total' => $maintenances->count()
        ]);
    }
}
