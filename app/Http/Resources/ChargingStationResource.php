<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargingStationResource extends JsonResource
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
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'site_id' => $this->site_id,
            'site' => new SiteResource($this->whenLoaded('site')),

            // Basic information
            'name' => $this->name,
            'station_code' => $this->station_code,
            'type' => $this->type,
            'connector_type' => $this->connector_type,
            'max_power_kw' => $this->max_power_kw,
            'status' => $this->status,
            'is_active' => $this->is_active,

            // Location
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,

            // Technical specifications
            'voltage_v' => $this->voltage_v,
            'amperage_a' => $this->amperage_a,
            'number_of_connectors' => $this->number_of_connectors,
            'supports_smart_charging' => $this->supports_smart_charging,
            'supports_payment' => $this->supports_payment,
            'network_provider' => $this->network_provider,
            'station_protocol' => $this->station_protocol,

            // Pricing
            'cost_per_kwh' => $this->cost_per_kwh,
            'cost_per_minute' => $this->cost_per_minute,
            'idle_fee_per_minute' => $this->idle_fee_per_minute,
            'currency' => $this->currency,

            // Access and restrictions
            'requires_authentication' => $this->requires_authentication,
            'access_type' => $this->access_type,
            'requires_membership' => $this->requires_membership,
            'access_hours' => $this->access_hours,
            'parking_restrictions' => $this->parking_restrictions,

            // Operational data
            'installation_date' => $this->installation_date,
            'last_maintenance_date' => $this->last_maintenance_date,
            'next_maintenance_date' => $this->next_maintenance_date,
            'warranty_expiry_date' => $this->warranty_expiry_date,

            // Manufacturer information
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'firmware_version' => $this->firmware_version,

            // Usage statistics
            'total_sessions' => $this->total_sessions,
            'total_energy_delivered_kwh' => $this->total_energy_delivered_kwh,
            'total_revenue' => $this->total_revenue,
            'average_session_duration_minutes' => $this->average_session_duration_minutes,
            'utilization_rate_percent' => $this->utilization_rate_percent,
            'last_session_date' => $this->last_session_date,

            // Additional fields
            'notes' => $this->notes,
            'qr_code' => $this->qr_code,
            'image_url' => $this->image_url,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
