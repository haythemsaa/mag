<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargingStationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'name' => 'required|string|max:255',
            'station_code' => 'nullable|string|max:50|unique:charging_stations,station_code',
            'type' => 'required|in:home,workplace,public,private,depot',
            'connector_type' => 'required|in:Type 2,CCS,CHAdeMO,Tesla Supercharger,Type 1,AC',
            'max_power_kw' => 'required|numeric|min:0|max:350',
            'status' => 'nullable|in:available,occupied,maintenance,offline,reserved',
            'is_active' => 'nullable|boolean',

            // Location
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',

            // Technical specifications
            'voltage_v' => 'nullable|numeric|min:0',
            'amperage_a' => 'nullable|numeric|min:0',
            'number_of_connectors' => 'nullable|integer|min:1|max:10',
            'supports_smart_charging' => 'nullable|boolean',
            'supports_payment' => 'nullable|boolean',
            'network_provider' => 'nullable|string|max:100',
            'station_protocol' => 'nullable|string|max:50',

            // Pricing
            'cost_per_kwh' => 'nullable|numeric|min:0',
            'cost_per_minute' => 'nullable|numeric|min:0',
            'idle_fee_per_minute' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',

            // Access and restrictions
            'requires_authentication' => 'nullable|boolean',
            'access_type' => 'nullable|in:public,private,semi-public',
            'requires_membership' => 'nullable|boolean',
            'access_hours' => 'nullable|string|max:255',
            'parking_restrictions' => 'nullable|string',

            // Operational data
            'installation_date' => 'nullable|date',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date',

            // Manufacturer information
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'firmware_version' => 'nullable|string|max:50',

            // Usage statistics (these will be auto-calculated but can be seeded)
            'total_sessions' => 'nullable|integer|min:0',
            'total_energy_delivered_kwh' => 'nullable|numeric|min:0',
            'total_revenue' => 'nullable|numeric|min:0',
            'average_session_duration_minutes' => 'nullable|numeric|min:0',
            'utilization_rate_percent' => 'nullable|numeric|min:0|max:100',
            'last_session_date' => 'nullable|date',

            // Additional fields
            'notes' => 'nullable|string',
            'qr_code' => 'nullable|string|max:500',
            'image_url' => 'nullable|url|max:500',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }

        if (!$this->has('status')) {
            $this->merge([
                'status' => 'available',
            ]);
        }
    }
}
