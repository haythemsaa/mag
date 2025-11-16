<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargingSessionRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'charging_station_id' => 'required|exists:charging_stations,id',
            'driver_id' => 'nullable|exists:users,id',

            'session_number' => 'nullable|string|max:50|unique:charging_sessions,session_number',
            'status' => 'nullable|in:in_progress,completed,interrupted,failed',

            // Battery levels
            'battery_level_start_percent' => 'required|integer|min:0|max:100',
            'battery_level_end_percent' => 'nullable|integer|min:0|max:100',

            // Session timing
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration_minutes' => 'nullable|integer|min:0',

            // Energy and cost
            'energy_delivered_kwh' => 'nullable|numeric|min:0',
            'cost_per_kwh' => 'required|numeric|min:0',
            'cost_per_minute' => 'nullable|numeric|min:0',
            'idle_time_minutes' => 'nullable|integer|min:0',
            'idle_fee' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',

            // Charging details
            'charging_power_kw' => 'nullable|numeric|min:0',
            'peak_power_kw' => 'nullable|numeric|min:0',
            'average_power_kw' => 'nullable|numeric|min:0',
            'connector_used' => 'nullable|string|max:50',

            // Payment and transaction
            'payment_method' => 'nullable|in:credit_card,fleet_card,mobile_app,subscription,free',
            'payment_status' => 'nullable|in:pending,completed,failed,refunded',
            'transaction_id' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',

            // Environmental impact
            'co2_saved_kg' => 'nullable|numeric|min:0',

            // Session quality
            'interruption_count' => 'nullable|integer|min:0',
            'error_codes' => 'nullable|string',

            // Location at start
            'start_latitude' => 'nullable|numeric|between:-90,90',
            'start_longitude' => 'nullable|numeric|between:-180,180',

            // Additional information
            'notes' => 'nullable|string',
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

        if (!$this->has('start_time')) {
            $this->merge([
                'start_time' => now(),
            ]);
        }

        if (!$this->has('status')) {
            $this->merge([
                'status' => 'in_progress',
            ]);
        }

        if (!$this->has('currency')) {
            $this->merge([
                'currency' => 'EUR',
            ]);
        }
    }
}
