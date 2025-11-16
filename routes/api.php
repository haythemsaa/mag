<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\FuelTransactionController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\CostController;
use App\Http\Controllers\Api\GpsPositionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InfractionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

// Authentication routes (public)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // User route (deprecated - use /me instead)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/live-fleet', [DashboardController::class, 'liveFleet']);

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

    // Sites
    Route::apiResource('sites', SiteController::class);

    // Workshops
    Route::apiResource('workshops', WorkshopController::class);
    Route::post('workshops/{id}/update-rating', [WorkshopController::class, 'updateRating']);

    // Contracts
    Route::get('contracts/expiring', [ContractController::class, 'expiring']);
    Route::apiResource('contracts', ContractController::class);

    // Costs
    Route::get('costs/statistics', [CostController::class, 'statistics']);
    Route::apiResource('costs', CostController::class);
    Route::post('costs/{id}/validate', [CostController::class, 'validate']);

    // GPS Positions
    Route::get('gps-positions/live-tracking', [GpsPositionController::class, 'liveTracking']);
    Route::get('gps-positions/geofence-alerts', [GpsPositionController::class, 'geofenceAlerts']);
    Route::get('gps-positions/vehicle/{vehicleId}/latest', [GpsPositionController::class, 'latest']);
    Route::get('gps-positions/vehicle/{vehicleId}/track', [GpsPositionController::class, 'track']);
    Route::apiResource('gps-positions', GpsPositionController::class);

    // Infractions
    Route::prefix('infractions')->group(function () {
        Route::get('/', [InfractionController::class, 'index']);
        Route::post('/', [InfractionController::class, 'store']);
        Route::get('/statistics', [InfractionController::class, 'statistics']);
        Route::get('/{infraction}', [InfractionController::class, 'show']);
        Route::put('/{infraction}', [InfractionController::class, 'update']);
        Route::delete('/{infraction}', [InfractionController::class, 'destroy']);
        Route::post('/{infraction}/pay', [InfractionController::class, 'markAsPaid']);
        Route::post('/{infraction}/contest', [InfractionController::class, 'contest']);
    });
});
