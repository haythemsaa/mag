<?php

use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\FuelTransactionController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // User route
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Organizations
    Route::apiResource('organizations', OrganizationController::class);
    Route::get('organizations/{id}/statistics', [OrganizationController::class, 'statistics']);
    Route::get('organizations/{id}/dashboard', [OrganizationController::class, 'dashboard']);

    // Vehicles
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/{id}/statistics', [VehicleController::class, 'statistics']);
    Route::post('vehicles/{id}/assign-driver', [VehicleController::class, 'assignDriver']);
    Route::delete('vehicles/{id}/unassign-driver', [VehicleController::class, 'unassignDriver']);
    Route::patch('vehicles/{id}/mileage', [VehicleController::class, 'updateMileage']);

    // Drivers
    Route::apiResource('drivers', DriverController::class);

    // Maintenances
    Route::get('maintenances/upcoming', [MaintenanceController::class, 'upcoming']);
    Route::get('maintenances/overdue', [MaintenanceController::class, 'overdue']);
    Route::apiResource('maintenances', MaintenanceController::class);
    Route::post('maintenances/{id}/complete', [MaintenanceController::class, 'complete']);

    // Fuel Transactions
    Route::get('fuel-transactions/statistics', [FuelTransactionController::class, 'statistics']);
    Route::post('fuel-transactions/detect-anomalies', [FuelTransactionController::class, 'detectAnomalies']);
    Route::apiResource('fuel-transactions', FuelTransactionController::class);
    Route::post('fuel-transactions/{id}/validate', [FuelTransactionController::class, 'validate']);
});
