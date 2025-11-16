<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
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
            'site_id' => $this->site_id,
            'registration_number' => $this->registration_number,
            'vin' => $this->vin,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'type' => $this->type,
            'category' => $this->category,
            'fuel_type' => $this->fuel_type,
            'status' => $this->status,
            'mileage' => $this->mileage,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_cost' => $this->acquisition_cost,
            'color' => $this->color,
            'seats' => $this->seats,
            'doors' => $this->doors,
            'engine_power' => $this->engine_power,

            // Calculated fields
            'tco' => $this->when($request->input('include_tco'), fn() => $this->calculateTCO()),
            'needs_maintenance' => $this->when($request->input('include_status'), fn() => $this->needsMaintenance()),

            // Relationships (only when loaded)
            'organization' => $this->whenLoaded('organization', fn() => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'site' => $this->whenLoaded('site', fn() => [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'city' => $this->site->city,
            ]),
            'current_driver' => $this->whenLoaded('currentDriver', fn() => new DriverResource($this->currentDriver)),
            'maintenances_count' => $this->when($this->relationLoaded('maintenances'), fn() => $this->maintenances->count()),
            'fuel_transactions_count' => $this->when($this->relationLoaded('fuelTransactions'), fn() => $this->fuelTransactions->count()),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
