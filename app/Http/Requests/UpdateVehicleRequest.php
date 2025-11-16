<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');
        return $this->user()->can('update', $vehicle);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vehicleId = $this->route('vehicle')->id ?? null;

        return [
            'site_id' => 'sometimes|nullable|exists:sites,id',
            'registration_number' => 'sometimes|string|max:20|unique:vehicles,registration_number,' . $vehicleId,
            'vin' => 'sometimes|nullable|string|max:17|unique:vehicles,vin,' . $vehicleId,
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'fuel_type' => 'sometimes|in:gasoline,diesel,electric,hybrid,lpg,cng',
            'status' => 'sometimes|in:active,inactive,maintenance,sold,scrapped',
            'category' => 'sometimes|in:car,van,truck,bus,motorcycle,utility',
            'ownership_type' => 'sometimes|in:owned,leased,rented',
            'mileage' => 'sometimes|integer|min:0',
            'engine_capacity' => 'sometimes|nullable|numeric|min:0',
            'horsepower' => 'sometimes|nullable|integer|min:0',
            'seats' => 'sometimes|nullable|integer|min:1|max:100',
            'load_capacity' => 'sometimes|nullable|numeric|min:0',
            'fuel_tank_capacity' => 'sometimes|nullable|numeric|min:0',
            'acquisition_date' => 'sometimes|nullable|date',
            'acquisition_price' => 'sometimes|nullable|numeric|min:0',
            'current_value' => 'sometimes|nullable|numeric|min:0',
            'insurance_expiry_date' => 'sometimes|nullable|date',
            'technical_inspection_date' => 'sometimes|nullable|date',
            'next_technical_inspection' => 'sometimes|nullable|date',
            'maintenance_interval' => 'sometimes|nullable|integer|min:1',
            'notes' => 'sometimes|nullable|string|max:1000',
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
            'registration_number.unique' => 'This registration number is already in use',
            'vin.unique' => 'This VIN is already in use',
            'year.max' => 'Year cannot be in the future',
            'fuel_type.in' => 'Invalid fuel type',
            'status.in' => 'Invalid status',
        ];
    }
}
