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

    // Vehicles
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/{id}/statistics', [VehicleController::class, 'statistics']);
    Route::post('vehicles/{id}/assign-driver', [VehicleController::class, 'assignDriver']);
    Route::delete('vehicles/{id}/unassign-driver', [VehicleController::class, 'unassignDriver']);
    Route::patch('vehicles/{id}/mileage', [VehicleController::class, 'updateMileage']);

    // Drivers
    Route::apiResource('drivers', DriverController::class);

    // Maintenances
    Route::apiResource('maintenances', MaintenanceController::class);

    // Fuel Transactions
    Route::apiResource('fuel-transactions', FuelTransactionController::class);
});
