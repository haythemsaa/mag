<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashcamEvent;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Dashcam Webhooks
 *
 * Public webhooks for dashcam provider integrations
 */
class DashcamWebhookController extends Controller
{
    /**
     * Generic webhook receiver
     *
     * Receives dashcam events from third-party providers.
     *
     * @bodyParam provider string required Provider name (mobileye, lytx, surfsight, smartwitness, samsara, geotab). Example: lytx
     * @bodyParam event_type string required Type of event. Example: harsh_braking
     * @bodyParam severity string required Event severity (low, medium, high, critical). Example: high
     * @bodyParam timestamp string required Event timestamp (ISO 8601). Example: 2025-11-16T12:30:00Z
     * @bodyParam vehicle_identifier string required Vehicle identification (registration, VIN, or device ID). Example: ABC-123
     * @bodyParam external_event_id string Provider's event ID. Example: evt_123456
     * @bodyParam latitude number Event latitude. Example: 36.8065
     * @bodyParam longitude number Event longitude. Example: 10.1815
     * @bodyParam speed_kmh number Speed at event time. Example: 85.5
     * @bodyParam video_url string URL to hosted video. Example: https://provider.com/videos/123
     */
    public function receive(Request $request): JsonResponse
    {
        try {
            // Validate basic structure
            $validated = $request->validate([
                'provider' => 'required|string',
                'event_type' => 'required|string',
                'severity' => 'required|string|in:low,medium,high,critical',
                'timestamp' => 'required|date',
                'vehicle_identifier' => 'required|string',
                'external_event_id' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'speed_kmh' => 'nullable|numeric',
                'speed_limit_kmh' => 'nullable|numeric',
                'g_force_x' => 'nullable|numeric',
                'g_force_y' => 'nullable|numeric',
                'g_force_z' => 'nullable|numeric',
                'max_g_force' => 'nullable|numeric',
                'video_url' => 'nullable|url',
                'video_thumbnail_url' => 'nullable|url',
                'video_duration_seconds' => 'nullable|integer',
                'video_available_until' => 'nullable|date',
                'device_id' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            // Find vehicle
            $vehicle = $this->findVehicle($validated['vehicle_identifier']);

            if (! $vehicle) {
                Log::warning('Dashcam webhook: Vehicle not found', [
                    'identifier' => $validated['vehicle_identifier'],
                    'provider' => $validated['provider'],
                ]);

                return response()->json([
                    'message' => 'Vehicle not found',
                    'identifier' => $validated['vehicle_identifier'],
                ], 404);
            }

            // Find driver (if vehicle has active assignment)
            $driver = $this->findActiveDriver($vehicle, $validated['timestamp']);

            // Map event type to our enum
            $eventType = $this->mapEventType($validated['event_type']);

            // Create event
            $event = DashcamEvent::create([
                'organization_id' => $vehicle->organization_id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver?->id,
                'event_type' => $eventType,
                'severity' => $validated['severity'],
                'status' => $this->determineInitialStatus($validated['severity']),
                'event_timestamp' => $validated['timestamp'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'speed_kmh' => $validated['speed_kmh'] ?? null,
                'speed_limit_kmh' => $validated['speed_limit_kmh'] ?? null,
                'g_force_x' => $validated['g_force_x'] ?? null,
                'g_force_y' => $validated['g_force_y'] ?? null,
                'g_force_z' => $validated['g_force_z'] ?? null,
                'max_g_force' => $validated['max_g_force'] ?? null,
                'dashcam_provider' => $validated['provider'],
                'external_event_id' => $validated['external_event_id'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'video_thumbnail_url' => $validated['video_thumbnail_url'] ?? null,
                'video_duration_seconds' => $validated['video_duration_seconds'] ?? null,
                'video_available_until' => $validated['video_available_until'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'webhook_payload' => $request->all(),
                'received_at' => now(),
            ]);

            Log::info('Dashcam event created', [
                'event_id' => $event->id,
                'event_number' => $event->event_number,
                'provider' => $validated['provider'],
                'type' => $eventType,
                'vehicle' => $vehicle->registration_number,
            ]);

            return response()->json([
                'message' => 'Event received successfully',
                'event_id' => $event->id,
                'event_number' => $event->event_number,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Dashcam webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error processing webhook',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mobileye-specific webhook
     *
     * Receives events from Mobileye dashcam systems.
     */
    public function mobileye(Request $request): JsonResponse
    {
        // Transform Mobileye format to generic format
        $genericPayload = $this->transformMobileyePayload($request->all());

        // Forward to generic receiver
        return $this->receive(new Request($genericPayload));
    }

    /**
     * Lytx-specific webhook
     *
     * Receives events from Lytx dashcam systems.
     */
    public function lytx(Request $request): JsonResponse
    {
        $genericPayload = $this->transformLytxPayload($request->all());

        return $this->receive(new Request($genericPayload));
    }

    /**
     * Surfsight-specific webhook
     *
     * Receives events from Surfsight dashcam systems.
     */
    public function surfsight(Request $request): JsonResponse
    {
        $genericPayload = $this->transformSurfsightPayload($request->all());

        return $this->receive(new Request($genericPayload));
    }

    /**
     * SmartWitness-specific webhook
     *
     * Receives events from SmartWitness dashcam systems.
     */
    public function smartwitness(Request $request): JsonResponse
    {
        $genericPayload = $this->transformSmartWitnessPayload($request->all());

        return $this->receive(new Request($genericPayload));
    }

    /**
     * Samsara-specific webhook
     *
     * Receives events from Samsara dashcam systems.
     */
    public function samsara(Request $request): JsonResponse
    {
        $genericPayload = $this->transformSamsaraPayload($request->all());

        return $this->receive(new Request($genericPayload));
    }

    /**
     * Geotab-specific webhook
     *
     * Receives events from Geotab dashcam systems.
     */
    public function geotab(Request $request): JsonResponse
    {
        $genericPayload = $this->transformGeotabPayload($request->all());

        return $this->receive(new Request($genericPayload));
    }

    /**
     * Find vehicle by various identifiers
     */
    protected function findVehicle(string $identifier): ?Vehicle
    {
        // Try by registration number
        $vehicle = Vehicle::where('registration_number', $identifier)->first();

        if ($vehicle) {
            return $vehicle;
        }

        // Try by VIN
        $vehicle = Vehicle::where('vin', $identifier)->first();

        if ($vehicle) {
            return $vehicle;
        }

        // Try by internal ID
        if (is_numeric($identifier)) {
            return Vehicle::find($identifier);
        }

        return null;
    }

    /**
     * Find active driver for vehicle at given time
     */
    protected function findActiveDriver(Vehicle $vehicle, string $timestamp): ?Driver
    {
        // For now, return assigned driver if exists
        // In production, this could check trip logs, assignments, etc.
        return $vehicle->driver;
    }

    /**
     * Map provider event type to our enum
     */
    protected function mapEventType(string $providerType): string
    {
        $mapping = [
            // Generic mappings
            'harsh_braking' => 'harsh_braking',
            'hard_braking' => 'harsh_braking',
            'harsh_acceleration' => 'harsh_acceleration',
            'hard_acceleration' => 'harsh_acceleration',
            'harsh_cornering' => 'harsh_cornering',
            'hard_turn' => 'harsh_cornering',
            'collision' => 'collision',
            'impact' => 'collision',
            'speeding' => 'speeding',
            'over_speed' => 'speeding',
            'distraction' => 'distraction',
            'drowsiness' => 'drowsiness',
            'fatigue' => 'drowsiness',
            'phone_usage' => 'phone_usage',
            'phone_use' => 'phone_usage',
            'cell_phone' => 'phone_usage',
            'smoking' => 'smoking',
            'no_seatbelt' => 'no_seatbelt',
            'seatbelt' => 'no_seatbelt',
            'lane_departure' => 'lane_departure',
            'ldw' => 'lane_departure',
            'forward_collision_warning' => 'forward_collision_warning',
            'fcw' => 'forward_collision_warning',
            'tailgating' => 'tailgating',
            'following_distance' => 'tailgating',
            'rolling_stop' => 'rolling_stop',
        ];

        $normalizedType = strtolower(str_replace(['-', '_', ' '], '_', $providerType));

        return $mapping[$normalizedType] ?? 'other';
    }

    /**
     * Determine initial status based on severity
     */
    protected function determineInitialStatus(string $severity): string
    {
        return match ($severity) {
            'critical' => 'coaching_required',
            'high' => 'coaching_required',
            default => 'pending_review',
        };
    }

    /**
     * Transform Mobileye payload to generic format
     */
    protected function transformMobileyePayload(array $payload): array
    {
        return [
            'provider' => 'mobileye',
            'event_type' => $payload['eventType'] ?? 'other',
            'severity' => $payload['severity'] ?? 'medium',
            'timestamp' => $payload['timestamp'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['vehicleId'] ?? $payload['vin'] ?? '',
            'external_event_id' => $payload['eventId'] ?? null,
            'latitude' => $payload['location']['latitude'] ?? null,
            'longitude' => $payload['location']['longitude'] ?? null,
            'speed_kmh' => $payload['speed'] ?? null,
            'video_url' => $payload['videoUrl'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Transform Lytx payload to generic format
     */
    protected function transformLytxPayload(array $payload): array
    {
        return [
            'provider' => 'lytx',
            'event_type' => $payload['event_type'] ?? 'other',
            'severity' => $payload['severity'] ?? 'medium',
            'timestamp' => $payload['event_time'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['vehicle']['registration'] ?? $payload['vehicle_id'] ?? '',
            'external_event_id' => $payload['event_id'] ?? null,
            'latitude' => $payload['gps']['lat'] ?? null,
            'longitude' => $payload['gps']['lon'] ?? null,
            'speed_kmh' => $payload['speed_kph'] ?? null,
            'g_force_x' => $payload['g_force']['x'] ?? null,
            'g_force_y' => $payload['g_force']['y'] ?? null,
            'g_force_z' => $payload['g_force']['z'] ?? null,
            'video_url' => $payload['video']['url'] ?? null,
            'video_thumbnail_url' => $payload['video']['thumbnail'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Transform Surfsight payload to generic format
     */
    protected function transformSurfsightPayload(array $payload): array
    {
        return [
            'provider' => 'surfsight',
            'event_type' => $payload['type'] ?? 'other',
            'severity' => $payload['priority'] ?? 'medium',
            'timestamp' => $payload['datetime'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['vehicle_ref'] ?? '',
            'external_event_id' => $payload['id'] ?? null,
            'latitude' => $payload['lat'] ?? null,
            'longitude' => $payload['lng'] ?? null,
            'speed_kmh' => $payload['speed'] ?? null,
            'video_url' => $payload['clip_url'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Transform SmartWitness payload to generic format
     */
    protected function transformSmartWitnessPayload(array $payload): array
    {
        return [
            'provider' => 'smartwitness',
            'event_type' => $payload['eventType'] ?? 'other',
            'severity' => $payload['severity'] ?? 'medium',
            'timestamp' => $payload['eventDateTime'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['vehicleRegistration'] ?? '',
            'external_event_id' => $payload['eventReference'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'speed_kmh' => $payload['speedKmh'] ?? null,
            'video_url' => $payload['videoLink'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Transform Samsara payload to generic format
     */
    protected function transformSamsaraPayload(array $payload): array
    {
        return [
            'provider' => 'samsara',
            'event_type' => $payload['eventType'] ?? 'other',
            'severity' => $this->mapSamsaraSeverity($payload['severity'] ?? 'normal'),
            'timestamp' => $payload['time'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['vehicle']['name'] ?? $payload['vehicle']['id'] ?? '',
            'external_event_id' => $payload['id'] ?? null,
            'latitude' => $payload['location']['latitude'] ?? null,
            'longitude' => $payload['location']['longitude'] ?? null,
            'speed_kmh' => isset($payload['speed']) ? $payload['speed'] * 1.60934 : null, // mph to kmh
            'video_url' => $payload['downloadUrl'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Transform Geotab payload to generic format
     */
    protected function transformGeotabPayload(array $payload): array
    {
        return [
            'provider' => 'geotab',
            'event_type' => $payload['diagnosticType'] ?? 'other',
            'severity' => $this->mapGeotabSeverity($payload['diagnosticType'] ?? ''),
            'timestamp' => $payload['dateTime'] ?? now()->toIso8601String(),
            'vehicle_identifier' => $payload['device']['serialNumber'] ?? $payload['device']['id'] ?? '',
            'external_event_id' => $payload['id'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'speed_kmh' => $payload['speed'] ?? null,
            'metadata' => $payload,
        ];
    }

    /**
     * Map Samsara severity to our enum
     */
    protected function mapSamsaraSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'critical',
            'severe', 'high' => 'high',
            'moderate', 'medium' => 'medium',
            default => 'low',
        };
    }

    /**
     * Map Geotab diagnostic type to severity
     */
    protected function mapGeotabSeverity(string $diagnosticType): string
    {
        $critical = ['Collision', 'Airbag', 'HarshBraking'];
        $high = ['HarshAcceleration', 'HarshCornering', 'Speeding'];

        if (in_array($diagnosticType, $critical)) {
            return 'critical';
        }

        if (in_array($diagnosticType, $high)) {
            return 'high';
        }

        return 'medium';
    }
}
