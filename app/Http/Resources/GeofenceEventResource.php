<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceEventResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'geofence_id' => $this->geofence_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'gps_position_id' => $this->gps_position_id,
            'event_type' => $this->event_type,
            'event_time' => $this->event_time,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed_kmh' => $this->speed_kmh,
            'alert_sent' => $this->alert_sent,
            'alert_sent_at' => $this->alert_sent_at,
            'notes' => $this->notes,

            // Relationships
            'geofence' => $this->whenLoaded('geofence', fn () => [
                'id' => $this->geofence->id,
                'name' => $this->geofence->name,
                'type' => $this->geofence->type,
            ]),

            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),

            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'first_name' => $this->driver->first_name,
                'last_name' => $this->driver->last_name,
            ]),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
