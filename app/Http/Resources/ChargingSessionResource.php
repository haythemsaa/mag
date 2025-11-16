<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargingSessionResource extends JsonResource
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
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'charging_station_id' => $this->charging_station_id,
            'charging_station' => new ChargingStationResource($this->whenLoaded('chargingStation')),
            'driver_id' => $this->driver_id,
            'driver' => new UserResource($this->whenLoaded('driver')),

            // Session identification
            'session_number' => $this->session_number,
            'status' => $this->status,

            // Battery levels
            'battery_level_start_percent' => $this->battery_level_start_percent,
            'battery_level_end_percent' => $this->battery_level_end_percent,
            'battery_charge_gained_percent' => $this->battery_level_end_percent
                ? $this->battery_level_end_percent - $this->battery_level_start_percent
                : null,

            // Session timing
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'duration_formatted' => $this->duration_minutes
                ? sprintf('%dh %dm', floor($this->duration_minutes / 60), $this->duration_minutes % 60)
                : null,

            // Energy and cost
            'energy_delivered_kwh' => $this->energy_delivered_kwh,
            'cost_per_kwh' => $this->cost_per_kwh,
            'cost_per_minute' => $this->cost_per_minute,
            'idle_time_minutes' => $this->idle_time_minutes,
            'idle_fee' => $this->idle_fee,
            'total_cost' => $this->total_cost,
            'currency' => $this->currency,

            // Charging details
            'charging_power_kw' => $this->charging_power_kw,
            'peak_power_kw' => $this->peak_power_kw,
            'average_power_kw' => $this->average_power_kw,
            'connector_used' => $this->connector_used,

            // Payment and transaction
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'transaction_id' => $this->transaction_id,
            'invoice_number' => $this->invoice_number,

            // Environmental impact
            'co2_saved_kg' => $this->co2_saved_kg,

            // Session quality
            'interruption_count' => $this->interruption_count,
            'error_codes' => $this->error_codes,

            // Location at start
            'start_latitude' => $this->start_latitude,
            'start_longitude' => $this->start_longitude,

            // Additional information
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
