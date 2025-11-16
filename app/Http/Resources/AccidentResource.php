<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accident_number' => $this->accident_number,
            'status' => $this->status,
            'severity' => $this->severity,
            'responsibility' => $this->responsibility,

            // Véhicule et conducteur
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id' => $this->driver->id,
                'full_name' => $this->driver->first_name . ' ' . $this->driver->last_name,
                'license_number' => $this->driver->license_number,
            ]),

            // Date et lieu
            'accident_date' => $this->accident_date?->toISOString(),
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Description
            'description' => $this->description,

            // Police
            'police_report' => $this->police_report,
            'police_report_number' => $this->police_report_number,
            'has_police_report' => $this->has_police_report,

            // Blessures
            'injuries' => $this->injuries,
            'injured_count' => $this->injured_count,

            // Coûts
            'estimated_cost' => $this->estimated_cost ? (float) $this->estimated_cost : null,
            'final_cost' => $this->final_cost ? (float) $this->final_cost : null,

            // Assurance
            'insurance_claim_number' => $this->insurance_claim_number,
            'insurance_claim_date' => $this->insurance_claim_date?->format('Y-m-d'),
            'insurance_status' => $this->insurance_status,
            'has_insurance_claim' => $this->has_insurance_claim,

            // Photos
            'photos' => $this->whenLoaded('photos'),

            // Informations calculées
            'duration_days' => $this->duration_days,

            // Notes
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
