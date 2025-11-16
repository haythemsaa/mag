<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('infractions.create');
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }

        if (!$this->has('status')) {
            $this->merge(['status' => 'received']);
        }
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'reference_number' => 'nullable|string|max:255',
            'type' => 'required|in:speeding,red_light,parking,phone,seatbelt,alcohol,dangerous_driving,stop_sign,wrong_way,other',
            'infraction_date' => 'required|date|before_or_equal:today',
            'infraction_time' => 'nullable|date_format:H:i',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'amount' => 'required|numeric|min:0',
            'reduced_amount' => 'nullable|numeric|min:0|lt:amount',
            'increased_amount' => 'nullable|numeric|min:0|gt:amount',
            'points_deducted' => 'nullable|integer|min:0|max:12',
            'due_date' => 'nullable|date|after:infraction_date',
            'reduced_due_date' => 'nullable|date|after:infraction_date|before:due_date',
            'recorded_speed' => 'nullable|numeric|min:0',
            'speed_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire',
            'vehicle_id.exists' => 'Le véhicule sélectionné n\'existe pas',
            'type.required' => 'Le type d\'infraction est obligatoire',
            'infraction_date.required' => 'La date d\'infraction est obligatoire',
            'infraction_date.before_or_equal' => 'La date d\'infraction ne peut pas être future',
            'amount.required' => 'Le montant est obligatoire',
            'amount.min' => 'Le montant doit être positif',
        ];
    }
}
