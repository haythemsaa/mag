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
use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\ChargingStationController;
use App\Http\Controllers\Api\ChargingSessionController;
use App\Http\Controllers\Api\TheftAlertController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\RouteStopController;
use App\Http\Controllers\Api\Mobile\MobileDriverController;
use App\Http\Controllers\Api\Mobile\MobileIncidentController;
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

    // Accidents
    Route::prefix('accidents')->group(function () {
        Route::get('/', [AccidentController::class, 'index']);
        Route::post('/', [AccidentController::class, 'store']);
        Route::get('/statistics', [AccidentController::class, 'statistics']);
        Route::get('/{accident}', [AccidentController::class, 'show']);
        Route::put('/{accident}', [AccidentController::class, 'update']);
        Route::delete('/{accident}', [AccidentController::class, 'destroy']);
        Route::post('/{accident}/expertised', [AccidentController::class, 'markAsExpertised']);
        Route::post('/{accident}/repaired', [AccidentController::class, 'markAsRepaired']);
        Route::post('/{accident}/close', [AccidentController::class, 'close']);
        Route::post('/{accident}/insurance-claim', [AccidentController::class, 'fileInsuranceClaim']);
    });

    // Geofences
    Route::prefix('geofences')->group(function () {
        Route::get('/', [GeofenceController::class, 'index']);
        Route::post('/', [GeofenceController::class, 'store']);
        Route::get('/events', [GeofenceController::class, 'events']);
        Route::get('/{geofence}', [GeofenceController::class, 'show']);
        Route::put('/{geofence}', [GeofenceController::class, 'update']);
        Route::delete('/{geofence}', [GeofenceController::class, 'destroy']);
        Route::get('/{geofence}/statistics', [GeofenceController::class, 'statistics']);
    });

    // Charging Stations
    Route::prefix('charging-stations')->group(function () {
        Route::get('/', [ChargingStationController::class, 'index']);
        Route::post('/', [ChargingStationController::class, 'store']);
        Route::get('/{charging_station}', [ChargingStationController::class, 'show']);
        Route::put('/{charging_station}', [ChargingStationController::class, 'update']);
        Route::delete('/{charging_station}', [ChargingStationController::class, 'destroy']);
        Route::get('/{charging_station}/statistics', [ChargingStationController::class, 'statistics']);
    });

    // Charging Sessions
    Route::prefix('charging-sessions')->group(function () {
        Route::get('/', [ChargingSessionController::class, 'index']);
        Route::post('/', [ChargingSessionController::class, 'store']);
        Route::get('/statistics', [ChargingSessionController::class, 'statistics']);
        Route::get('/{charging_session}', [ChargingSessionController::class, 'show']);
        Route::put('/{charging_session}', [ChargingSessionController::class, 'update']);
        Route::delete('/{charging_session}', [ChargingSessionController::class, 'destroy']);
        Route::post('/{charging_session}/complete', [ChargingSessionController::class, 'complete']);
    });

    // Theft Alerts
    Route::prefix('theft-alerts')->group(function () {
        Route::get('/', [TheftAlertController::class, 'index']);
        Route::post('/', [TheftAlertController::class, 'store']);
        Route::get('/statistics', [TheftAlertController::class, 'statistics']);
        Route::get('/{theftAlert}', [TheftAlertController::class, 'show']);
        Route::put('/{theftAlert}', [TheftAlertController::class, 'update']);
        Route::delete('/{theftAlert}', [TheftAlertController::class, 'destroy']);
        Route::post('/{theftAlert}/assign', [TheftAlertController::class, 'assign']);
        Route::post('/{theftAlert}/investigate', [TheftAlertController::class, 'investigate']);
        Route::post('/{theftAlert}/false-alarm', [TheftAlertController::class, 'markFalseAlarm']);
        Route::post('/{theftAlert}/confirm-theft', [TheftAlertController::class, 'confirmTheft']);
        Route::post('/{theftAlert}/resolve', [TheftAlertController::class, 'resolve']);
        Route::post('/{theftAlert}/notify-police', [TheftAlertController::class, 'notifyPolice']);
        Route::post('/{theftAlert}/insurance-claim', [TheftAlertController::class, 'linkInsuranceClaim']);
    });

    // Routes
    Route::prefix('routes')->group(function () {
        Route::get('/', [RouteController::class, 'index']);
        Route::post('/', [RouteController::class, 'store']);
        Route::get('/statistics', [RouteController::class, 'statistics']);
        Route::get('/{route}', [RouteController::class, 'show']);
        Route::put('/{route}', [RouteController::class, 'update']);
        Route::delete('/{route}', [RouteController::class, 'destroy']);
        Route::post('/{route}/start', [RouteController::class, 'start']);
        Route::post('/{route}/complete', [RouteController::class, 'complete']);
        Route::post('/{route}/cancel', [RouteController::class, 'cancel']);
        Route::post('/{route}/assign-vehicle', [RouteController::class, 'assignVehicle']);
        Route::post('/{route}/assign-driver', [RouteController::class, 'assignDriver']);
        Route::post('/{route}/optimize', [RouteController::class, 'optimize']);

        // Route Stops
        Route::get('/{route}/stops', [RouteStopController::class, 'index']);
        Route::post('/{route}/stops', [RouteStopController::class, 'store']);
        Route::get('/{route}/stops/{stop}', [RouteStopController::class, 'show']);
        Route::put('/{route}/stops/{stop}', [RouteStopController::class, 'update']);
        Route::delete('/{route}/stops/{stop}', [RouteStopController::class, 'destroy']);
        Route::post('/{route}/stops/{stop}/arrive', [RouteStopController::class, 'arrive']);
        Route::post('/{route}/stops/{stop}/start-service', [RouteStopController::class, 'startService']);
        Route::post('/{route}/stops/{stop}/complete', [RouteStopController::class, 'complete']);
        Route::post('/{route}/stops/{stop}/skip', [RouteStopController::class, 'skip']);
        Route::post('/{route}/stops/{stop}/fail', [RouteStopController::class, 'fail']);
        Route::post('/{route}/stops/{stop}/upload-signature', [RouteStopController::class, 'uploadSignature']);
        Route::post('/{route}/stops/{stop}/upload-photo', [RouteStopController::class, 'uploadPhoto']);
    });

    // Mobile API - Driver
    Route::prefix('mobile/driver')->group(function () {
        Route::get('/profile', [MobileDriverController::class, 'profile']);
        Route::put('/profile', [MobileDriverController::class, 'updateProfile']);
        Route::get('/vehicle', [MobileDriverController::class, 'assignedVehicle']);
        Route::post('/location', [MobileDriverController::class, 'updateLocation']);
        Route::get('/trips', [MobileDriverController::class, 'tripHistory']);
        Route::post('/change-password', [MobileDriverController::class, 'changePassword']);
    });

    // Mobile API - Incidents
    Route::prefix('mobile/incidents')->group(function () {
        Route::post('/report', [MobileIncidentController::class, 'report']);
        Route::get('/', [MobileIncidentController::class, 'list']);
        Route::post('/{accident_id}/photos', [MobileIncidentController::class, 'uploadPhotos']);
        Route::delete('/{accident_id}/photos/{photo_id}', [MobileIncidentController::class, 'deletePhoto']);
    });
});
