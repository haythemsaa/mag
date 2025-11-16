<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
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
            'contract_type' => $this->contract_type,
            'contract_number' => $this->contract_number,
            'supplier' => $this->supplier,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'monthly_cost' => $this->monthly_cost,
            'mileage_limit' => $this->mileage_limit,
            'excess_mileage_cost' => $this->excess_mileage_cost,
            'status' => $this->status,
            'auto_renewal' => $this->auto_renewal,
            'notice_period_days' => $this->notice_period_days,
            'terms' => $this->terms,
            'notes' => $this->notes,

            // Calculated fields
            'is_expiring_soon' => $this->when(
                $request->input('include_status'),
                fn() => $this->status === 'active' && $this->end_date && $this->end_date->isBetween(now(), now()->addDays(60))
            ),
            'days_until_expiry' => $this->when(
                $request->input('include_status'),
                fn() => $this->end_date ? now()->diffInDays($this->end_date, false) : null
            ),
            'total_contract_cost' => $this->when(
                $request->input('include_calculations'),
                fn() => $this->calculateTotalCost()
            ),
            'duration_months' => $this->when(
                $request->input('include_calculations'),
                fn() => $this->start_date && $this->end_date ? $this->start_date->diffInMonths($this->end_date) : null
            ),

            // Relationships
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
