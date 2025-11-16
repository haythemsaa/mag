<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelTransactionResource extends JsonResource
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
            'driver_id' => $this->driver_id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'transaction_time' => $this->transaction_time,
            'fuel_type' => $this->fuel_type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_cost' => $this->total_cost,
            'mileage' => $this->mileage,
            'fuel_card_number' => $this->fuel_card_number,
            'station_name' => $this->station_name,
            'station_location' => $this->station_location,
            'validated' => $this->validated,
            'validated_by' => $this->validated_by,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'anomaly_detected' => $this->anomaly_detected,
            'anomaly_type' => $this->anomaly_type,
            'notes' => $this->notes,

            // Calculated fields
            'consumption_per_100km' => $this->when(
                $request->input('include_calculations'),
                fn() => $this->calculateConsumption()
            ),
            'price_variance' => $this->when(
                $request->input('include_calculations'),
                fn() => $this->calculatePriceVariance()
            ),

            // Relationships
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id' => $this->driver->id,
                'full_name' => "{$this->driver->first_name} {$this->driver->last_name}",
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
