<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('vehicles.create');
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
            'site_id' => 'nullable|exists:sites,id',
            'registration_number' => 'required|string|max:20|unique:vehicles,registration_number',
            'vin' => 'nullable|string|max:17|unique:vehicles,vin',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'fuel_type' => 'required|in:gasoline,diesel,electric,hybrid,lpg,cng',
            'status' => 'nullable|in:active,inactive,maintenance,sold,scrapped',
            'category' => 'nullable|in:car,van,truck,bus,motorcycle,utility',
            'ownership_type' => 'nullable|in:owned,leased,rented',
            'mileage' => 'nullable|integer|min:0',
            'engine_capacity' => 'nullable|numeric|min:0',
            'horsepower' => 'nullable|integer|min:0',
            'seats' => 'nullable|integer|min:1|max:100',
            'load_capacity' => 'nullable|numeric|min:0',
            'fuel_tank_capacity' => 'nullable|numeric|min:0',
            'acquisition_date' => 'nullable|date',
            'acquisition_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'insurance_expiry_date' => 'nullable|date|after:today',
            'technical_inspection_date' => 'nullable|date',
            'next_technical_inspection' => 'nullable|date|after:technical_inspection_date',
            'maintenance_interval' => 'nullable|integer|min:1',
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
            'registration_number.required' => 'Registration number is required',
            'registration_number.unique' => 'This registration number is already in use',
            'vin.unique' => 'This VIN is already in use',
            'make.required' => 'Vehicle make is required',
            'model.required' => 'Vehicle model is required',
            'year.required' => 'Manufacturing year is required',
            'year.max' => 'Year cannot be in the future',
            'fuel_type.required' => 'Fuel type is required',
            'fuel_type.in' => 'Invalid fuel type',
            'insurance_expiry_date.after' => 'Insurance must not be expired',
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
                'status' => 'active',
            ]);
        }
    }
}
