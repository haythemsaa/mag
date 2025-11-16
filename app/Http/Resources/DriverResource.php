<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'license_type' => $this->license_type,
            'license_expiry_date' => $this->license_expiry_date?->toDateString(),
            'license_is_expiring' => $this->when($request->input('include_status'), fn() => $this->licenseExpiringSoon()),
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'eco_driving_score' => $this->eco_driving_score,

            // Relationships
            'organization' => $this->whenLoaded('organization', fn() => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'assigned_vehicles_count' => $this->when($this->relationLoaded('vehicles'), fn() => $this->vehicles->count()),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
