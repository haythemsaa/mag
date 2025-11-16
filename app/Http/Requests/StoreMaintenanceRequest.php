<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('maintenances.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'workshop_id' => 'nullable|exists:workshops,id',
            'type' => 'required|in:preventive,corrective,inspection,tire_change,oil_change,other',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'description' => 'required|string|max:500',
            'scheduled_date' => 'nullable|date',
            'mileage_at_maintenance' => 'nullable|integer|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Vehicle is required',
            'vehicle_id.exists' => 'Selected vehicle does not exist',
            'type.required' => 'Maintenance type is required',
            'type.in' => 'Invalid maintenance type',
            'description.required' => 'Description is required',
            'description.max' => 'Description cannot exceed 500 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-inject organization_id from authenticated user if not provided
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }

        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }

        // Calculate total_cost if labor_cost and parts_cost are provided
        if ($this->has('labor_cost') || $this->has('parts_cost')) {
            $laborCost = (float) ($this->input('labor_cost', 0));
            $partsCost = (float) ($this->input('parts_cost', 0));

            $this->merge([
                'total_cost' => $laborCost + $partsCost,
            ]);
        }
    }
}
