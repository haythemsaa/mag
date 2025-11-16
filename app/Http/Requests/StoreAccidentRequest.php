<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accidents.create');
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }

        if (!$this->has('status')) {
            $this->merge(['status' => 'declared']);
        }

        if (!$this->has('responsibility')) {
            $this->merge(['responsibility' => 'unknown']);
        }
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'accident_date' => 'required|date|before_or_equal:now',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'severity' => 'required|in:minor,moderate,severe,total_loss',
            'responsibility' => 'sometimes|in:driver,third_party,shared,unknown',
            'description' => 'required|string',
            'police_report' => 'boolean',
            'police_report_number' => 'nullable|string|max:255',
            'injuries' => 'boolean',
            'injured_count' => 'nullable|integer|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire',
            'driver_id.required' => 'Le conducteur est obligatoire',
            'accident_date.required' => 'La date de l\'accident est obligatoire',
            'severity.required' => 'La gravité de l\'accident est obligatoire',
            'description.required' => 'La description de l\'accident est obligatoire',
        ];
    }
}
