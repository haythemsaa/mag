<?php

namespace Database\Factories;

use App\Models\Geofence;
use App\Models\Organization;
use App\Models\TheftAlert;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TheftAlert>
 */
class TheftAlertFactory extends Factory
{
    protected $model = TheftAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $detectedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $type = $this->faker->randomElement([
            'movement_outside_hours',
            'geofence_violation',
            'gps_signal_loss',
            'unauthorized_ignition',
            'towing_detected',
            'speed_anomaly',
            'route_deviation',
        ]);

        $severity = $this->determineSeverity($type);

        return [
            'organization_id' => Organization::factory(),
            'vehicle_id' => Vehicle::factory(),
            'geofence_id' => null,
            'type' => $type,
            'status' => 'pending',
            'severity' => $severity,
            'description' => $this->getDescriptionForType($type),
            'detected_at' => $detectedAt,
            'resolved_at' => null,

            // Location data
            'latitude' => $this->faker->latitude(45.0, 50.0),
            'longitude' => $this->faker->longitude(-5.0, 8.0),
            'address' => $this->faker->address(),

            // Technical data
            'gps_signal_available' => $this->faker->boolean(85),
            'signal_strength' => $this->faker->numberBetween(20, 100),
            'speed_kmh' => $this->faker->optional(0.7)->randomFloat(2, 0, 130),
            'heading' => $this->faker->optional(0.7)->randomFloat(2, 0, 360),
            'engine_on' => $this->faker->boolean(40),
            'ignition_on' => $this->faker->boolean(45),

            // Alert triggers
            'outside_work_hours' => $type === 'movement_outside_hours' || $type === 'unauthorized_ignition',
            'geofence_exit_unauthorized' => $type === 'geofence_violation',
            'gps_jamming_suspected' => $type === 'gps_signal_loss',
            'towing_movement_detected' => $type === 'towing_detected',
            'speed_anomaly_detected' => $type === 'speed_anomaly',

            // Response tracking (initially null)
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'investigation_started_at' => null,
            'investigation_notes' => null,
            'resolution_notes' => null,

            // Notification tracking
            'sms_sent' => false,
            'sms_sent_at' => null,
            'email_sent' => false,
            'email_sent_at' => null,
            'push_notification_sent' => false,
            'push_notification_sent_at' => null,

            // Police/Insurance
            'police_notified' => false,
            'police_notified_at' => null,
            'police_reference_number' => null,
            'insurance_claim_number' => null,

            // Additional data
            'additional_data' => null,
            'admin_notes' => null,
        ];
    }

    /**
     * Determine severity based on type
     */
    protected function determineSeverity(string $type): string
    {
        return match ($type) {
            'unauthorized_ignition', 'towing_detected' => 'critical',
            'geofence_violation', 'gps_signal_loss' => 'high',
            'movement_outside_hours', 'speed_anomaly' => $this->faker->randomElement(['medium', 'high']),
            default => 'medium',
        };
    }

    /**
     * Get description based on type
     */
    protected function getDescriptionForType(string $type): string
    {
        return match ($type) {
            'movement_outside_hours' => 'Vehicle movement detected outside authorized work hours',
            'geofence_violation' => 'Vehicle has exited authorized geofence boundary',
            'gps_signal_loss' => 'GPS signal lost - possible jamming detected',
            'unauthorized_ignition' => 'Unauthorized ignition detected outside work hours',
            'towing_detected' => 'Vehicle movement without ignition - possible towing',
            'speed_anomaly' => 'Unusual speed pattern detected',
            'route_deviation' => 'Vehicle has deviated from expected route',
            default => 'Suspicious activity detected',
        };
    }

    /**
     * State for pending alerts
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'investigation_started_at' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * State for investigating alerts
     */
    public function investigating(): static
    {
        return $this->state(function (array $attributes) {
            $detectedAt = $attributes['detected_at'];
            $assignedAt = $this->faker->dateTimeBetween($detectedAt, '+30 minutes');
            $investigationStarted = $this->faker->dateTimeBetween($assignedAt, '+15 minutes');

            return [
                'status' => 'investigating',
                'assigned_to_user_id' => User::factory(),
                'assigned_at' => $assignedAt,
                'investigation_started_at' => $investigationStarted,
                'investigation_notes' => $this->faker->text(200),
                'resolved_at' => null,
            ];
        });
    }

    /**
     * State for false alarm alerts
     */
    public function falseAlarm(): static
    {
        return $this->state(function (array $attributes) {
            $detectedAt = $attributes['detected_at'];
            $resolvedAt = $this->faker->dateTimeBetween($detectedAt, '+2 hours');

            return [
                'status' => 'false_alarm',
                'assigned_to_user_id' => User::factory(),
                'assigned_at' => $this->faker->dateTimeBetween($detectedAt, '+15 minutes'),
                'investigation_started_at' => $this->faker->dateTimeBetween($detectedAt, '+20 minutes'),
                'investigation_notes' => 'Contacted driver - authorized use',
                'resolution_notes' => $this->faker->randomElement([
                    'Driver confirmed authorized use of vehicle',
                    'Emergency maintenance required outside hours',
                    'Approved overtime work',
                    'GPS calibration issue',
                ]),
                'resolved_at' => $resolvedAt,
            ];
        });
    }

    /**
     * State for confirmed theft
     */
    public function confirmedTheft(): static
    {
        return $this->state(function (array $attributes) {
            $detectedAt = $attributes['detected_at'];
            $policeNotifiedAt = $this->faker->dateTimeBetween($detectedAt, '+1 hour');

            return [
                'status' => 'confirmed_theft',
                'severity' => 'critical',
                'assigned_to_user_id' => User::factory(),
                'assigned_at' => $this->faker->dateTimeBetween($detectedAt, '+10 minutes'),
                'investigation_started_at' => $this->faker->dateTimeBetween($detectedAt, '+15 minutes'),
                'investigation_notes' => 'Driver confirmed vehicle was stolen. Unable to locate vehicle.',
                'resolution_notes' => 'Theft confirmed - police notified',
                'police_notified' => true,
                'police_notified_at' => $policeNotifiedAt,
                'police_reference_number' => 'POL-' . $this->faker->year() . '-' . $this->faker->numberBetween(10000, 99999),
                'insurance_claim_number' => $this->faker->optional(0.7)->regexify('INS-[0-9]{4}-[0-9]{5}'),
                'sms_sent' => true,
                'sms_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+5 minutes'),
                'email_sent' => true,
                'email_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+5 minutes'),
                'push_notification_sent' => true,
                'push_notification_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+2 minutes'),
            ];
        });
    }

    /**
     * State for resolved alerts
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes) {
            $detectedAt = $attributes['detected_at'];
            $resolvedAt = $this->faker->dateTimeBetween($detectedAt, '+48 hours');

            return [
                'status' => 'resolved',
                'assigned_to_user_id' => User::factory(),
                'assigned_at' => $this->faker->dateTimeBetween($detectedAt, '+10 minutes'),
                'investigation_started_at' => $this->faker->dateTimeBetween($detectedAt, '+15 minutes'),
                'investigation_notes' => 'Vehicle located and recovered',
                'resolution_notes' => $this->faker->randomElement([
                    'Vehicle recovered by police',
                    'Issue resolved - technical malfunction',
                    'Vehicle returned by unauthorized user',
                ]),
                'resolved_at' => $resolvedAt,
                'police_notified' => $this->faker->boolean(60),
                'police_notified_at' => $this->faker->boolean(60) ? $this->faker->dateTimeBetween($detectedAt, '+30 minutes') : null,
                'police_reference_number' => $this->faker->optional(0.6)->regexify('POL-[0-9]{4}-[0-9]{5}'),
            ];
        });
    }

    /**
     * State for critical severity
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
            'type' => $this->faker->randomElement(['unauthorized_ignition', 'towing_detected']),
        ]);
    }

    /**
     * State for high severity
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'high',
            'type' => $this->faker->randomElement(['geofence_violation', 'gps_signal_loss', 'movement_outside_hours']),
        ]);
    }

    /**
     * State for movement outside hours
     */
    public function movementOutsideHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'movement_outside_hours',
            'description' => 'Vehicle movement detected outside authorized work hours',
            'outside_work_hours' => true,
            'detected_at' => $this->faker->dateTimeBetween('-30 days', 'now')->setTime(
                $this->faker->randomElement([2, 3, 22, 23]),
                $this->faker->numberBetween(0, 59)
            ),
        ]);
    }

    /**
     * State for geofence violation
     */
    public function geofenceViolation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'geofence_violation',
            'description' => 'Vehicle has exited authorized geofence boundary',
            'geofence_id' => Geofence::factory(),
            'geofence_exit_unauthorized' => true,
        ]);
    }

    /**
     * State for unauthorized ignition
     */
    public function unauthorizedIgnition(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'unauthorized_ignition',
            'severity' => 'critical',
            'description' => 'Unauthorized ignition detected outside work hours',
            'ignition_on' => true,
            'engine_on' => true,
            'outside_work_hours' => true,
            'detected_at' => $this->faker->dateTimeBetween('-30 days', 'now')->setTime(
                $this->faker->randomElement([1, 2, 3, 22, 23]),
                $this->faker->numberBetween(0, 59)
            ),
        ]);
    }

    /**
     * State for towing detected
     */
    public function towingDetected(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'towing_detected',
            'severity' => 'critical',
            'description' => 'Vehicle movement without ignition - possible towing',
            'ignition_on' => false,
            'engine_on' => false,
            'speed_kmh' => $this->faker->randomFloat(2, 15, 50),
            'towing_movement_detected' => true,
        ]);
    }

    /**
     * State with notifications sent
     */
    public function notificationsSent(): static
    {
        return $this->state(function (array $attributes) {
            $detectedAt = $attributes['detected_at'];

            return [
                'sms_sent' => true,
                'sms_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+5 minutes'),
                'email_sent' => true,
                'email_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+5 minutes'),
                'push_notification_sent' => true,
                'push_notification_sent_at' => $this->faker->dateTimeBetween($detectedAt, '+2 minutes'),
            ];
        });
    }
}
