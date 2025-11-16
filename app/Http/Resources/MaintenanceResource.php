<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
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
            'vehicle_id' => $this->vehicle_id,
            'workshop_id' => $this->workshop_id,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'completed_date' => $this->completed_date?->toDateString(),
            'mileage_at_maintenance' => $this->mileage_at_maintenance,
            'labor_cost' => $this->labor_cost,
            'parts_cost' => $this->parts_cost,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,

            // Calculated fields
            'is_overdue' => $this->when(
                $request->input('include_status'),
                fn() => $this->status !== 'completed' && $this->scheduled_date && $this->scheduled_date->isPast()
            ),
            'days_until_due' => $this->when(
                $request->input('include_status'),
                fn() => $this->scheduled_date ? now()->diffInDays($this->scheduled_date, false) : null
            ),

            // Relationships
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),
            'workshop' => $this->whenLoaded('workshop', fn() => [
                'id' => $this->workshop->id,
                'name' => $this->workshop->name,
                'city' => $this->workshop->city,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
