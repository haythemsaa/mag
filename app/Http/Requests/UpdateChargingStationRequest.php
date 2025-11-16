<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChargingStationRequest extends FormRequest
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
        $stationId = $this->route('charging_station')?->id;

        return [
            'site_id' => 'nullable|exists:sites,id',
            'name' => 'sometimes|string|max:255',
            'station_code' => 'sometimes|string|max:50|unique:charging_stations,station_code,' . $stationId,
            'type' => 'sometimes|in:home,workplace,public,private,depot',
            'connector_type' => 'sometimes|in:Type 2,CCS,CHAdeMO,Tesla Supercharger,Type 1,AC',
            'max_power_kw' => 'sometimes|numeric|min:0|max:350',
            'status' => 'sometimes|in:available,occupied,maintenance,offline,reserved',
            'is_active' => 'sometimes|boolean',

            // Location
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:100',

            // Technical specifications
            'voltage_v' => 'sometimes|numeric|min:0',
            'amperage_a' => 'sometimes|numeric|min:0',
            'number_of_connectors' => 'sometimes|integer|min:1|max:10',
            'supports_smart_charging' => 'sometimes|boolean',
            'supports_payment' => 'sometimes|boolean',
            'network_provider' => 'sometimes|string|max:100',
            'station_protocol' => 'sometimes|string|max:50',

            // Pricing
            'cost_per_kwh' => 'sometimes|numeric|min:0',
            'cost_per_minute' => 'sometimes|numeric|min:0',
            'idle_fee_per_minute' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',

            // Access and restrictions
            'requires_authentication' => 'sometimes|boolean',
            'access_type' => 'sometimes|in:public,private,semi-public',
            'requires_membership' => 'sometimes|boolean',
            'access_hours' => 'sometimes|string|max:255',
            'parking_restrictions' => 'sometimes|string',

            // Operational data
            'installation_date' => 'sometimes|date',
            'last_maintenance_date' => 'sometimes|date',
            'next_maintenance_date' => 'sometimes|date',
            'warranty_expiry_date' => 'sometimes|date',

            // Manufacturer information
            'manufacturer' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'serial_number' => 'sometimes|string|max:100',
            'firmware_version' => 'sometimes|string|max:50',

            // Usage statistics
            'total_sessions' => 'sometimes|integer|min:0',
            'total_energy_delivered_kwh' => 'sometimes|numeric|min:0',
            'total_revenue' => 'sometimes|numeric|min:0',
            'average_session_duration_minutes' => 'sometimes|numeric|min:0',
            'utilization_rate_percent' => 'sometimes|numeric|min:0|max:100',
            'last_session_date' => 'sometimes|date',

            // Additional fields
            'notes' => 'sometimes|string',
            'qr_code' => 'sometimes|string|max:500',
            'image_url' => 'sometimes|url|max:500',
        ];
    }
}
