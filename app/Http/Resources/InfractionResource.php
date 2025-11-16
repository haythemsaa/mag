<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'infraction_number' => $this->infraction_number,
            'reference_number' => $this->reference_number,
            'type' => $this->type,
            'status' => $this->status,

            // Véhicule et conducteur
            'vehicle' => $this->whenLoaded('vehicle', fn() => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'make' => $this->vehicle->make,
                'model' => $this->vehicle->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => $this->driver ? [
                'id' => $this->driver->id,
                'full_name' => $this->driver->first_name . ' ' . $this->driver->last_name,
                'license_number' => $this->driver->license_number,
            ] : null),

            // Date et lieu
            'infraction_date' => $this->infraction_date?->format('Y-m-d'),
            'infraction_time' => $this->infraction_time?->format('H:i'),
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            // Montants
            'amount' => (float) $this->amount,
            'reduced_amount' => $this->reduced_amount ? (float) $this->reduced_amount : null,
            'increased_amount' => $this->increased_amount ? (float) $this->increased_amount : null,
            'amount_to_pay' => $this->amount_to_pay,
            'points_deducted' => $this->points_deducted,

            // Dates importantes
            'due_date' => $this->due_date?->format('Y-m-d'),
            'reduced_due_date' => $this->reduced_due_date?->format('Y-m-d'),
            'paid_date' => $this->paid_date?->format('Y-m-d'),
            'contested_date' => $this->contested_date?->format('Y-m-d'),

            // Vitesse (si excès de vitesse)
            'recorded_speed' => $this->recorded_speed ? (float) $this->recorded_speed : null,
            'speed_limit' => $this->speed_limit ? (float) $this->speed_limit : null,
            'speed_excess' => $this->speed_excess,

            // Paiement
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,

            // Informations calculées
            'is_overdue' => $this->is_overdue,
            'days_until_due' => $this->days_until_due,

            // Pièces jointes
            'attachments' => $this->whenLoaded('attachments'),

            // Notes
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
