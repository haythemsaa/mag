<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
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
            'route_number' => $this->route_number,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'planned_date' => $this->planned_date?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Distance metrics
            'planned_distance_km' => $this->planned_distance_km,
            'actual_distance_km' => $this->actual_distance_km,
            'distance_variance_km' => $this->distance_variance_km,
            'distance_variance_percent' => $this->distance_variance_percent,

            // Duration metrics
            'planned_duration_minutes' => $this->planned_duration_minutes,
            'actual_duration_minutes' => $this->actual_duration_minutes,
            'duration_variance_minutes' => $this->duration_variance_minutes,
            'duration_variance_percent' => $this->duration_variance_percent,

            // Cost tracking
            'estimated_fuel_cost' => $this->estimated_fuel_cost,
            'actual_fuel_cost' => $this->actual_fuel_cost,
            'estimated_total_cost' => $this->estimated_total_cost,
            'actual_total_cost' => $this->actual_total_cost,

            // Optimization
            'optimization_method' => $this->optimization_method,
            'is_optimized' => $this->is_optimized,
            'stops_count' => $this->stops_count,
            'completed_stops_count' => $this->completed_stops_count,

            // Route metrics
            'fuel_consumption_liters' => $this->fuel_consumption_liters,
            'average_speed_kmh' => $this->average_speed_kmh,
            'idle_time_minutes' => $this->idle_time_minutes,
            'break_time_minutes' => $this->break_time_minutes,

            // Relationships
            'vehicle' => $this->whenLoaded('vehicle', function () {
                return $this->vehicle ? [
                    'id' => $this->vehicle->id,
                    'registration_number' => $this->vehicle->registration_number,
                    'make' => $this->vehicle->make,
                    'model' => $this->vehicle->model,
                ] : null;
            }),

            'driver' => $this->whenLoaded('driver', function () {
                return $this->driver ? [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                    'phone' => $this->driver->phone,
                ] : null;
            }),

            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ];
            }),

            'stops' => RouteStopResource::collection($this->whenLoaded('stops')),

            // Additional data
            'optimization_params' => $this->optimization_params,
            'waypoints' => $this->waypoints,
            'notes' => $this->notes,
            'completion_notes' => $this->completion_notes,

            // Computed fields
            'progress_percentage' => $this->getProgressPercentage(),
            'estimated_arrival' => $this->getEstimatedArrival()?->toIso8601String(),
            'can_start' => $this->canStart(),
            'can_complete' => $this->canComplete(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
