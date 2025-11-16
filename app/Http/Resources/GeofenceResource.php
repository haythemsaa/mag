<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'shape' => $this->shape,

            // Circle properties
            'center_latitude' => $this->when($this->shape === 'circle', $this->center_latitude),
            'center_longitude' => $this->when($this->shape === 'circle', $this->center_longitude),
            'radius_meters' => $this->when($this->shape === 'circle', $this->radius_meters),

            // Polygon properties
            'polygon_coordinates' => $this->when($this->shape === 'polygon', $this->polygon_coordinates),

            // Alert settings
            'alert_on_entry' => $this->alert_on_entry,
            'alert_on_exit' => $this->alert_on_exit,
            'is_active' => $this->is_active,

            // Time restrictions
            'active_from_time' => $this->active_from_time,
            'active_to_time' => $this->active_to_time,
            'active_days' => $this->active_days,

            // Address info
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'color' => $this->color,

            // Computed attributes
            'has_time_restrictions' => $this->has_time_restrictions,
            'total_events_count' => $this->total_events_count,

            // Relationships
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
