<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FuelTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FuelTransaction::with(['organization', 'vehicle', 'driver']);

        // Filter by organization
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by driver
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        // Filter by fuel type
        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        // Filter by validated status
        if ($request->has('validated')) {
            $query->where('validated', $request->boolean('validated'));
        }

        // Filter by anomaly detection
        if ($request->has('anomaly_detected')) {
            $query->where('anomaly_detected', $request->boolean('anomaly_detected'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('station_name', 'LIKE', "%{$search}%")
                    ->orWhere('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('card_number', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        return response()->json($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'transaction_date' => 'required|date',
            'station_name' => 'required|string|max:255',
            'fuel_type' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
            'mileage' => 'nullable|integer|min:0',
            'payment_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Calculate average price for anomaly detection
        $averagePrice = FuelTransaction::where('organization_id', $data['organization_id'])
            ->where('fuel_type', $data['fuel_type'])
            ->where('transaction_date', '>=', now()->subDays(30))
            ->avg('unit_price');

        // Detect price anomaly if we have historical data
        if ($averagePrice && $averagePrice > 0) {
            $transaction = new FuelTransaction($data);
            if ($transaction->detectPriceAnomaly($averagePrice, 0.15)) {
                $data['anomaly_detected'] = true;
                $data['anomaly_reason'] = 'Price deviation from 30-day average: ' .
                    number_format(abs($data['unit_price'] - $averagePrice) / $averagePrice * 100, 1) . '%';
            }
        }

        // Set default values
        $data['validated'] = $data['validated'] ?? false;
        $data['anomaly_detected'] = $data['anomaly_detected'] ?? false;

        $fuelTransaction = FuelTransaction::create($data);
        $fuelTransaction->load(['organization', 'vehicle', 'driver']);

        return response()->json([
            'message' => 'Fuel transaction created successfully',
            'data' => $fuelTransaction
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $transaction = FuelTransaction::with([
            'organization',
            'vehicle',
            'driver'
        ])->findOrFail($id);

        // Calculate consumption if previous transaction exists
        $previousTransaction = FuelTransaction::where('vehicle_id', $transaction->vehicle_id)
            ->where('transaction_date', '<', $transaction->transaction_date)
            ->orderBy('transaction_date', 'desc')
            ->first();

        if ($previousTransaction && $previousTransaction->mileage) {
            $transaction->calculated_consumption = $transaction->calculateConsumption($previousTransaction->mileage);
        }

        return response()->json([
            'data' => $transaction
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $transaction = FuelTransaction::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'driver_id' => 'nullable|exists:drivers,id',
            'transaction_date' => 'date',
            'station_name' => 'string|max:255',
            'quantity' => 'numeric|min:0',
            'unit_price' => 'numeric|min:0',
            'total_cost' => 'numeric|min:0',
            'mileage' => 'nullable|integer|min:0',
            'validated' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction->update($request->all());
        $transaction->load(['organization', 'vehicle', 'driver']);

        return response()->json([
            'message' => 'Fuel transaction updated successfully',
            'data' => $transaction
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $transaction = FuelTransaction::findOrFail($id);

        // Only allow deletion of non-validated transactions
        if ($transaction->validated) {
            return response()->json([
                'message' => 'Cannot delete validated fuel transaction'
            ], 409);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Fuel transaction deleted successfully'
        ]);
    }

    /**
     * Validate a fuel transaction
     */
    public function validate(string $id): JsonResponse
    {
        $transaction = FuelTransaction::findOrFail($id);

        $transaction->validated = true;
        $transaction->save();

        return response()->json([
            'message' => 'Fuel transaction validated successfully',
            'data' => $transaction
        ]);
    }

    /**
     * Detect anomalies in fuel transactions
     */
    public function detectAnomalies(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');
        $vehicleId = $request->get('vehicle_id');

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        $query = FuelTransaction::where('organization_id', $organizationId)
            ->where('validated', false);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $transactions = $query->get();
        $anomaliesDetected = 0;

        foreach ($transactions as $transaction) {
            // Get average price for this fuel type in the last 30 days
            $averagePrice = FuelTransaction::where('organization_id', $organizationId)
                ->where('fuel_type', $transaction->fuel_type)
                ->where('transaction_date', '>=', now()->subDays(30))
                ->where('validated', true)
                ->avg('unit_price');

            if ($averagePrice && $averagePrice > 0) {
                if ($transaction->detectPriceAnomaly($averagePrice, 0.15)) {
                    $transaction->anomaly_detected = true;
                    $transaction->anomaly_reason = 'Price deviation from 30-day average: ' .
                        number_format(abs($transaction->unit_price - $averagePrice) / $averagePrice * 100, 1) . '%';
                    $transaction->save();
                    $anomaliesDetected++;
                }
            }
        }

        return response()->json([
            'message' => 'Anomaly detection completed',
            'transactions_checked' => $transactions->count(),
            'anomalies_detected' => $anomaliesDetected
        ]);
    }

    /**
     * Get fuel statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');
        $vehicleId = $request->get('vehicle_id');
        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());

        if (!$organizationId) {
            return response()->json([
                'message' => 'organization_id is required'
            ], 422);
        }

        $query = FuelTransaction::where('organization_id', $organizationId)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $transactions = $query->get();

        $stats = [
            'total_transactions' => $transactions->count(),
            'total_quantity' => $transactions->sum('quantity'),
            'total_cost' => $transactions->sum('total_cost'),
            'average_unit_price' => $transactions->avg('unit_price'),
            'validated_count' => $transactions->where('validated', true)->count(),
            'anomaly_count' => $transactions->where('anomaly_detected', true)->count(),
            'by_fuel_type' => $transactions->groupBy('fuel_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_quantity' => $group->sum('quantity'),
                    'total_cost' => $group->sum('total_cost'),
                    'average_price' => $group->avg('unit_price'),
                ];
            }),
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
