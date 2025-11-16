<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Models\GpsPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Mobile API - Driver
 *
 * APIs for mobile driver application
 */
class MobileDriverController extends Controller
{
    /**
     * Get driver profile
     *
     * Returns the authenticated driver's profile information including
     * assigned vehicle, upcoming maintenance, and recent notifications.
     *
     * @authenticated
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Jean Dupont",
     *     "email": "jean@example.com",
     *     "phone": "+33612345678",
     *     "driver_license": "ABC123456",
     *     "assigned_vehicle": {
     *       "id": 1,
     *       "registration_number": "AB-123-CD",
     *       "make": "Renault",
     *       "model": "Kangoo"
     *     }
     *   }
     * }
     */
    public function profile(Request $request): UserResource
    {
        $user = $request->user()->load(['assignedVehicle', 'organization']);

        return new UserResource($user);
    }

    /**
     * Update driver profile
     *
     * Allows drivers to update their profile information like phone number.
     *
     * @authenticated
     *
     * @bodyParam phone string The driver's phone number. Example: +33612345678
     * @bodyParam emergency_contact_name string Emergency contact name. Example: Marie Dupont
     * @bodyParam emergency_contact_phone string Emergency contact phone. Example: +33698765432
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Jean Dupont",
     *     "phone": "+33612345678"
     *   }
     * }
     */
    public function updateProfile(Request $request): UserResource
    {
        $validated = $request->validate([
            'phone' => 'sometimes|string|max:20',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:20',
        ]);

        $request->user()->update($validated);

        return new UserResource($request->user()->fresh());
    }

    /**
     * Get assigned vehicle
     *
     * Returns the vehicle currently assigned to the driver with current status,
     * fuel level, maintenance alerts, and mileage.
     *
     * @authenticated
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "registration_number": "AB-123-CD",
     *     "make": "Renault",
     *     "model": "Kangoo",
     *     "mileage_km": 125000,
     *     "fuel_level_percent": 75,
     *     "status": "available",
     *     "next_maintenance_at": "2025-12-01",
     *     "upcoming_maintenance": []
     *   }
     * }
     *
     * @response 404 {
     *   "message": "No vehicle assigned to this driver"
     * }
     */
    public function assignedVehicle(Request $request): VehicleResource|JsonResponse
    {
        $vehicle = $request->user()->assignedVehicle;

        if (!$vehicle) {
            return response()->json([
                'message' => 'No vehicle assigned to this driver',
            ], 404);
        }

        return new VehicleResource($vehicle->load(['organization', 'maintenances' => function ($query) {
            $query->where('status', 'scheduled')
                ->where('scheduled_date', '>=', now())
                ->orderBy('scheduled_date')
                ->limit(5);
        }]));
    }

    /**
     * Update vehicle location
     *
     * Drivers can update their vehicle's GPS position from the mobile app.
     * This creates a new GPS position record for tracking.
     *
     * @authenticated
     *
     * @bodyParam latitude numeric required The latitude. Example: 48.8566
     * @bodyParam longitude numeric required The longitude. Example: 2.3522
     * @bodyParam speed_kmh numeric The speed in km/h. Example: 60
     * @bodyParam heading numeric The heading/direction in degrees (0-360). Example: 180
     * @bodyParam altitude_m numeric The altitude in meters. Example: 100
     * @bodyParam accuracy_m numeric The GPS accuracy in meters. Example: 5
     *
     * @response 201 {
     *   "message": "Location updated successfully",
     *   "data": {
     *     "id": 1234,
     *     "latitude": 48.8566,
     *     "longitude": 2.3522,
     *     "recorded_at": "2025-11-16T10:30:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "message": "No vehicle assigned to this driver"
     * }
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $vehicle = $request->user()->assignedVehicle;

        if (!$vehicle) {
            return response()->json([
                'message' => 'No vehicle assigned to this driver',
            ], 404);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:300',
            'heading' => 'nullable|numeric|min:0|max:360',
            'altitude_m' => 'nullable|numeric',
            'accuracy_m' => 'nullable|numeric|min:0',
        ]);

        $position = GpsPosition::create([
            'organization_id' => $request->user()->organization_id,
            'vehicle_id' => $vehicle->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed_kmh' => $validated['speed_kmh'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'altitude_m' => $validated['altitude_m'] ?? null,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => [
                'id' => $position->id,
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'recorded_at' => $position->recorded_at,
            ],
        ], 201);
    }

    /**
     * Get trip history
     *
     * Returns the driver's recent trips with start/end locations, distance,
     * and duration.
     *
     * @authenticated
     *
     * @queryParam limit integer Number of trips to return. Defaults to 20. Example: 10
     * @queryParam from_date date Filter trips from this date. Example: 2025-11-01
     * @queryParam to_date date Filter trips until this date. Example: 2025-11-16
     *
     * @response 200 {
     *   "data": {
     *     "trips": [
     *       {
     *         "date": "2025-11-16",
     *         "start_time": "08:00:00",
     *         "end_time": "09:30:00",
     *         "duration_minutes": 90,
     *         "distance_km": 45.5,
     *         "start_address": "Paris",
     *         "end_address": "Lyon"
     *       }
     *     ],
     *     "summary": {
     *       "total_trips": 150,
     *       "total_distance_km": 5250,
     *       "total_duration_hours": 180
     *     }
     *   }
     * }
     */
    public function tripHistory(Request $request): JsonResponse
    {
        $vehicle = $request->user()->assignedVehicle;

        if (!$vehicle) {
            return response()->json([
                'message' => 'No vehicle assigned to this driver',
                'data' => [
                    'trips' => [],
                    'summary' => [
                        'total_trips' => 0,
                        'total_distance_km' => 0,
                        'total_duration_hours' => 0,
                    ],
                ],
            ], 200);
        }

        $limit = min($request->input('limit', 20), 100);

        $query = GpsPosition::where('vehicle_id', $vehicle->id)
            ->orderBy('recorded_at', 'desc');

        if ($request->filled('from_date')) {
            $query->where('recorded_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('recorded_at', '<=', $request->to_date);
        }

        $positions = $query->limit($limit * 2)->get();

        // Group positions into trips (simplified - in production, use more sophisticated trip detection)
        $trips = [];
        $currentTrip = null;

        foreach ($positions as $position) {
            if (!$currentTrip) {
                $currentTrip = [
                    'start_time' => $position->recorded_at,
                    'start_latitude' => $position->latitude,
                    'start_longitude' => $position->longitude,
                    'positions' => [$position],
                ];
            } else {
                // If more than 30 minutes gap, consider it a new trip
                $timeDiff = $position->recorded_at->diffInMinutes($currentTrip['start_time']);
                if ($timeDiff > 30) {
                    // Close current trip
                    $lastPos = end($currentTrip['positions']);
                    $trips[] = [
                        'date' => $currentTrip['start_time']->toDateString(),
                        'start_time' => $currentTrip['start_time']->toTimeString(),
                        'end_time' => $lastPos->recorded_at->toTimeString(),
                        'duration_minutes' => $currentTrip['start_time']->diffInMinutes($lastPos->recorded_at),
                        'distance_km' => $this->calculateTripDistance($currentTrip['positions']),
                        'start_address' => 'N/A', // Would need geocoding service
                        'end_address' => 'N/A',
                    ];

                    // Start new trip
                    $currentTrip = [
                        'start_time' => $position->recorded_at,
                        'start_latitude' => $position->latitude,
                        'start_longitude' => $position->longitude,
                        'positions' => [$position],
                    ];
                } else {
                    $currentTrip['positions'][] = $position;
                }
            }

            if (count($trips) >= $limit) {
                break;
            }
        }

        $summary = [
            'total_trips' => count($trips),
            'total_distance_km' => array_sum(array_column($trips, 'distance_km')),
            'total_duration_hours' => round(array_sum(array_column($trips, 'duration_minutes')) / 60, 1),
        ];

        return response()->json([
            'data' => [
                'trips' => $trips,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Change password
     *
     * Allows drivers to change their password from the mobile app.
     *
     * @authenticated
     *
     * @bodyParam current_password string required Current password. Example: old_password123
     * @bodyParam new_password string required New password (min 8 characters). Example: new_password123
     * @bodyParam new_password_confirmation string required Confirm new password. Example: new_password123
     *
     * @response 200 {
     *   "message": "Password changed successfully"
     * }
     *
     * @response 422 {
     *   "message": "Current password is incorrect"
     * }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Calculate total distance of a trip from GPS positions
     */
    private function calculateTripDistance(array $positions): float
    {
        if (count($positions) < 2) {
            return 0;
        }

        $totalDistance = 0;
        for ($i = 0; $i < count($positions) - 1; $i++) {
            $totalDistance += $this->haversineDistance(
                $positions[$i]->latitude,
                $positions[$i]->longitude,
                $positions[$i + 1]->latitude,
                $positions[$i + 1]->longitude
            );
        }

        return round($totalDistance, 2);
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
