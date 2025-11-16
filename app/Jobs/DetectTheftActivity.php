<?php

namespace App\Jobs;

use App\Models\GpsPosition;
use App\Models\TheftAlert;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectTheftActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public ?GpsPosition $position = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if vehicle is not active or doesn't have GPS tracking
        if ($this->vehicle->status === 'decommissioned') {
            return;
        }

        // Get the latest position if not provided
        $position = $this->position ?? GpsPosition::where('vehicle_id', $this->vehicle->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$position) {
            return;
        }

        // Run all detection algorithms
        $this->checkMovementOutsideWorkHours($position);
        $this->checkGeofenceViolations($position);
        $this->checkGpsSignalLoss($position);
        $this->checkUnauthorizedIgnition($position);
        $this->checkTowingMovement($position);
        $this->checkSpeedAnomalies($position);
    }

    /**
     * Check if vehicle is moving outside of defined work hours
     */
    protected function checkMovementOutsideWorkHours(GpsPosition $position): void
    {
        // Define work hours (8 AM - 6 PM, Monday-Friday)
        $recordedAt = Carbon::parse($position->recorded_at);
        $isWorkday = $recordedAt->isWeekday();
        $hour = $recordedAt->hour;
        $isWorkHours = $hour >= 8 && $hour < 18;

        // Check if vehicle is moving (speed > 5 km/h)
        $isMoving = $position->speed_kmh && $position->speed_kmh > 5;

        if ($isMoving && (!$isWorkday || !$isWorkHours)) {
            // Check if there's already a recent alert for this
            $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                ->where('type', 'movement_outside_hours')
                ->where('detected_at', '>=', now()->subHours(2))
                ->whereIn('status', ['pending', 'investigating'])
                ->first();

            if (!$existingAlert) {
                $this->createAlert([
                    'type' => 'movement_outside_hours',
                    'severity' => $this->determineSeverity('movement_outside_hours', $position),
                    'description' => "Vehicle movement detected outside work hours at {$recordedAt->format('Y-m-d H:i:s')}",
                    'position' => $position,
                    'triggers' => [
                        'outside_work_hours' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Check if vehicle has exited authorized geofences
     */
    protected function checkGeofenceViolations(GpsPosition $position): void
    {
        // Get active geofences for this vehicle's organization
        $geofences = $this->vehicle->organization->geofences()
            ->where('is_active', true)
            ->where('type', 'restricted')
            ->get();

        foreach ($geofences as $geofence) {
            // Check if position is inside geofence
            $isInside = $this->isPointInPolygon(
                $position->latitude,
                $position->longitude,
                $geofence->coordinates
            );

            // If vehicle should be inside geofence but isn't
            if (!$isInside) {
                $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                    ->where('type', 'geofence_violation')
                    ->where('geofence_id', $geofence->id)
                    ->where('detected_at', '>=', now()->subHours(1))
                    ->whereIn('status', ['pending', 'investigating'])
                    ->first();

                if (!$existingAlert) {
                    $this->createAlert([
                        'type' => 'geofence_violation',
                        'severity' => $this->determineSeverity('geofence_violation', $position),
                        'description' => "Vehicle has exited authorized geofence: {$geofence->name}",
                        'position' => $position,
                        'geofence_id' => $geofence->id,
                        'triggers' => [
                            'geofence_exit_unauthorized' => true,
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * Check for GPS signal loss (potential jamming)
     */
    protected function checkGpsSignalLoss(GpsPosition $position): void
    {
        // Get recent positions to check for signal loss pattern
        $recentPositions = GpsPosition::where('vehicle_id', $this->vehicle->id)
            ->where('recorded_at', '>=', now()->subMinutes(30))
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentPositions->count() < 3) {
            return;
        }

        // Check for sudden signal loss while vehicle was in motion
        $signalLossCount = $recentPositions->where('accuracy_m', '>', 100)->count();
        $totalCount = $recentPositions->count();

        if ($signalLossCount / $totalCount > 0.5) {
            $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                ->where('type', 'gps_signal_loss')
                ->where('detected_at', '>=', now()->subHours(1))
                ->whereIn('status', ['pending', 'investigating'])
                ->first();

            if (!$existingAlert) {
                $this->createAlert([
                    'type' => 'gps_signal_loss',
                    'severity' => 'high',
                    'description' => 'GPS signal loss detected - possible jamming attempt',
                    'position' => $position,
                    'triggers' => [
                        'gps_jamming_suspected' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Check for unauthorized ignition
     */
    protected function checkUnauthorizedIgnition(GpsPosition $position): void
    {
        // Check if ignition is on outside work hours
        if (!$position->ignition_on) {
            return;
        }

        $recordedAt = Carbon::parse($position->recorded_at);
        $isWorkday = $recordedAt->isWeekday();
        $hour = $recordedAt->hour;
        $isWorkHours = $hour >= 8 && $hour < 18;

        if (!$isWorkday || !$isWorkHours) {
            $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                ->where('type', 'unauthorized_ignition')
                ->where('detected_at', '>=', now()->subHours(1))
                ->whereIn('status', ['pending', 'investigating'])
                ->first();

            if (!$existingAlert) {
                $this->createAlert([
                    'type' => 'unauthorized_ignition',
                    'severity' => 'critical',
                    'description' => "Unauthorized ignition detected at {$recordedAt->format('Y-m-d H:i:s')}",
                    'position' => $position,
                    'triggers' => [
                        'outside_work_hours' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Check for towing movement patterns
     */
    protected function checkTowingMovement(GpsPosition $position): void
    {
        // Get recent positions to analyze movement pattern
        $recentPositions = GpsPosition::where('vehicle_id', $this->vehicle->id)
            ->where('recorded_at', '>=', now()->subMinutes(15))
            ->orderBy('recorded_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentPositions->count() < 3) {
            return;
        }

        // Check for movement without ignition (towing pattern)
        $movingWithoutIgnition = $recentPositions->filter(function ($pos) {
            return $pos->speed_kmh > 10 && !$pos->ignition_on;
        })->count();

        if ($movingWithoutIgnition >= 2) {
            $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                ->where('type', 'towing_detected')
                ->where('detected_at', '>=', now()->subHours(1))
                ->whereIn('status', ['pending', 'investigating'])
                ->first();

            if (!$existingAlert) {
                $this->createAlert([
                    'type' => 'towing_detected',
                    'severity' => 'critical',
                    'description' => 'Vehicle movement detected without ignition - possible towing',
                    'position' => $position,
                    'triggers' => [
                        'towing_movement_detected' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Check for speed anomalies
     */
    protected function checkSpeedAnomalies(GpsPosition $position): void
    {
        if (!$position->speed_kmh) {
            return;
        }

        // Get average speed for this vehicle over the past week
        $avgSpeed = GpsPosition::where('vehicle_id', $this->vehicle->id)
            ->where('recorded_at', '>=', now()->subWeek())
            ->whereNotNull('speed_kmh')
            ->avg('speed_kmh');

        // Check for excessive speed (2x average or > 130 km/h)
        if ($avgSpeed && $position->speed_kmh > ($avgSpeed * 2) && $position->speed_kmh > 80) {
            $existingAlert = TheftAlert::where('vehicle_id', $this->vehicle->id)
                ->where('type', 'speed_anomaly')
                ->where('detected_at', '>=', now()->subHours(1))
                ->whereIn('status', ['pending', 'investigating'])
                ->first();

            if (!$existingAlert) {
                $this->createAlert([
                    'type' => 'speed_anomaly',
                    'severity' => $this->determineSeverity('speed_anomaly', $position),
                    'description' => "Unusual speed detected: {$position->speed_kmh} km/h (average: " . round($avgSpeed, 1) . " km/h)",
                    'position' => $position,
                    'triggers' => [
                        'speed_anomaly_detected' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Create a theft alert
     */
    protected function createAlert(array $data): void
    {
        $position = $data['position'];

        $alert = TheftAlert::create([
            'organization_id' => $this->vehicle->organization_id,
            'vehicle_id' => $this->vehicle->id,
            'geofence_id' => $data['geofence_id'] ?? null,
            'type' => $data['type'],
            'status' => 'pending',
            'severity' => $data['severity'],
            'description' => $data['description'],
            'detected_at' => $position->recorded_at,
            'latitude' => $position->latitude,
            'longitude' => $position->longitude,
            'gps_signal_available' => ($position->accuracy_m ?? 0) < 100,
            'signal_strength' => $this->calculateSignalStrength($position->accuracy_m),
            'speed_kmh' => $position->speed_kmh,
            'heading' => $position->heading,
            'engine_on' => $position->engine_on ?? false,
            'ignition_on' => $position->ignition_on ?? false,
            'outside_work_hours' => $data['triggers']['outside_work_hours'] ?? false,
            'geofence_exit_unauthorized' => $data['triggers']['geofence_exit_unauthorized'] ?? false,
            'gps_jamming_suspected' => $data['triggers']['gps_jamming_suspected'] ?? false,
            'towing_movement_detected' => $data['triggers']['towing_movement_detected'] ?? false,
            'speed_anomaly_detected' => $data['triggers']['speed_anomaly_detected'] ?? false,
        ]);

        Log::warning("Theft alert created", [
            'alert_id' => $alert->id,
            'alert_number' => $alert->alert_number,
            'type' => $alert->type,
            'vehicle_id' => $this->vehicle->id,
            'registration' => $this->vehicle->registration_number,
        ]);

        // TODO: Send notifications to organization admins
        // dispatch(new SendTheftAlertNotifications($alert));
    }

    /**
     * Determine severity based on alert type and context
     */
    protected function determineSeverity(string $type, GpsPosition $position): string
    {
        return match ($type) {
            'unauthorized_ignition', 'towing_detected' => 'critical',
            'geofence_violation', 'gps_signal_loss' => 'high',
            'movement_outside_hours' => Carbon::parse($position->recorded_at)->hour < 6 || Carbon::parse($position->recorded_at)->hour > 22 ? 'high' : 'medium',
            'speed_anomaly' => $position->speed_kmh > 130 ? 'high' : 'medium',
            default => 'medium',
        };
    }

    /**
     * Calculate signal strength from accuracy
     */
    protected function calculateSignalStrength(?float $accuracy): ?int
    {
        if ($accuracy === null) {
            return null;
        }

        // Convert accuracy to signal strength (0-100)
        // Lower accuracy = better signal
        if ($accuracy < 5) {
            return 100;
        } elseif ($accuracy < 10) {
            return 90;
        } elseif ($accuracy < 20) {
            return 75;
        } elseif ($accuracy < 50) {
            return 50;
        } elseif ($accuracy < 100) {
            return 25;
        }

        return 10;
    }

    /**
     * Check if a point is inside a polygon (Ray Casting Algorithm)
     */
    protected function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $count = count($polygon);
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lng'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lng'];

            $intersect = (($yi > $lng) != ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
