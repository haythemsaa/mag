<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TheftAlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_number' => $this->alert_number,
            'type' => $this->type,
            'status' => $this->status,
            'severity' => $this->severity,
            'description' => $this->description,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),

            // Location data
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,

            // Technical data
            'gps_signal_available' => $this->gps_signal_available,
            'signal_strength' => $this->signal_strength,
            'speed_kmh' => $this->speed_kmh,
            'heading' => $this->heading,
            'engine_on' => $this->engine_on,
            'ignition_on' => $this->ignition_on,

            // Alert triggers
            'triggers' => [
                'outside_work_hours' => $this->outside_work_hours,
                'geofence_exit_unauthorized' => $this->geofence_exit_unauthorized,
                'gps_jamming_suspected' => $this->gps_jamming_suspected,
                'towing_movement_detected' => $this->towing_movement_detected,
                'speed_anomaly_detected' => $this->speed_anomaly_detected,
            ],

            // Response tracking
            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                    'email' => $this->assignedTo->email,
                ];
            }),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'investigation_started_at' => $this->investigation_started_at?->toIso8601String(),
            'investigation_notes' => $this->investigation_notes,
            'resolution_notes' => $this->resolution_notes,

            // Notifications
            'notifications' => [
                'sms_sent' => $this->sms_sent,
                'sms_sent_at' => $this->sms_sent_at?->toIso8601String(),
                'email_sent' => $this->email_sent,
                'email_sent_at' => $this->email_sent_at?->toIso8601String(),
                'push_notification_sent' => $this->push_notification_sent,
                'push_notification_sent_at' => $this->push_notification_sent_at?->toIso8601String(),
            ],

            // Police/Insurance
            'police_notified' => $this->police_notified,
            'police_notified_at' => $this->police_notified_at?->toIso8601String(),
            'police_reference_number' => $this->police_reference_number,
            'insurance_claim_number' => $this->insurance_claim_number,

            // Relationships
            'vehicle' => $this->whenLoaded('vehicle', function () {
                return [
                    'id' => $this->vehicle->id,
                    'registration_number' => $this->vehicle->registration_number,
                    'make' => $this->vehicle->make,
                    'model' => $this->vehicle->model,
                    'year' => $this->vehicle->year,
                ];
            }),
            'geofence' => $this->whenLoaded('geofence', function () {
                return $this->geofence ? [
                    'id' => $this->geofence->id,
                    'name' => $this->geofence->name,
                    'type' => $this->geofence->type,
                ] : null;
            }),
            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ];
            }),

            // Additional data
            'additional_data' => $this->additional_data,
            'admin_notes' => $this->admin_notes,

            // Computed fields
            'is_resolved' => $this->isResolved(),
            'is_critical' => $this->isCritical(),
            'response_time_minutes' => $this->getResponseTimeMinutes(),
            'resolution_time_hours' => $this->getResolutionTimeHours(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
