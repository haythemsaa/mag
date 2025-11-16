<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteStopResource extends JsonResource
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
            'route_id' => $this->route_id,
            'stop_number' => $this->stop_number,
            'optimized_stop_number' => $this->optimized_stop_number,
            'type' => $this->type,
            'status' => $this->status,

            // Location
            'location_name' => $this->location_name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Contact
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,

            // Timing
            'planned_arrival' => $this->planned_arrival?->toIso8601String(),
            'planned_departure' => $this->planned_departure?->toIso8601String(),
            'actual_arrival' => $this->actual_arrival?->toIso8601String(),
            'actual_departure' => $this->actual_departure?->toIso8601String(),
            'service_duration_minutes' => $this->service_duration_minutes,
            'actual_service_duration_minutes' => $this->actual_service_duration_minutes,

            // Time window
            'time_window_start' => $this->time_window_start,
            'time_window_end' => $this->time_window_end,

            // Distance/duration
            'distance_from_previous_km' => $this->distance_from_previous_km,
            'duration_from_previous_minutes' => $this->duration_from_previous_minutes,

            // Tasks
            'instructions' => $this->instructions,
            'notes' => $this->notes,
            'requires_signature' => $this->requires_signature,
            'requires_photo' => $this->requires_photo,

            // Completion
            'signature_path' => $this->signature_path,
            'photo_paths' => $this->photo_paths,
            'completion_notes' => $this->completion_notes,
            'failure_reason' => $this->failure_reason,

            // Package details
            'reference_number' => $this->reference_number,
            'package_count' => $this->package_count,
            'package_weight_kg' => $this->package_weight_kg,
            'package_volume_m3' => $this->package_volume_m3,

            // Priority
            'priority' => $this->priority,

            // Computed fields
            'is_within_time_window' => $this->isWithinTimeWindow(),
            'is_late' => $this->isLate(),
            'delay_minutes' => $this->getDelayMinutes(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
