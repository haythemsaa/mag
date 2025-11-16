<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostResource extends JsonResource
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
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'description' => $this->description,
            'amount' => $this->amount,
            'vat_amount' => $this->vat_amount,
            'vat_deductible' => $this->vat_deductible,
            'date' => $this->date?->toDateString(),
            'invoice_number' => $this->invoice_number,
            'supplier' => $this->supplier,
            'payment_method' => $this->payment_method,
            'validated' => $this->validated,
            'validated_by' => $this->validated_by,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'notes' => $this->notes,

            // Calculated fields
            'total_with_vat' => $this->when(
                $request->input('include_calculations'),
                fn() => $this->amount + $this->vat_amount
            ),
            'is_recent' => $this->when(
                $request->input('include_status'),
                fn() => $this->date && $this->date->isAfter(now()->subDays(30))
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
