<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChargingSessionRequest extends FormRequest
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
        $sessionId = $this->route('charging_session')?->id;

        return [
            'session_number' => 'sometimes|string|max:50|unique:charging_sessions,session_number,' . $sessionId,
            'status' => 'sometimes|in:in_progress,completed,interrupted,failed',

            // Battery levels
            'battery_level_start_percent' => 'sometimes|integer|min:0|max:100',
            'battery_level_end_percent' => 'sometimes|integer|min:0|max:100',

            // Session timing
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'duration_minutes' => 'sometimes|integer|min:0',

            // Energy and cost
            'energy_delivered_kwh' => 'sometimes|numeric|min:0',
            'cost_per_kwh' => 'sometimes|numeric|min:0',
            'cost_per_minute' => 'sometimes|numeric|min:0',
            'idle_time_minutes' => 'sometimes|integer|min:0',
            'idle_fee' => 'sometimes|numeric|min:0',
            'total_cost' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',

            // Charging details
            'charging_power_kw' => 'sometimes|numeric|min:0',
            'peak_power_kw' => 'sometimes|numeric|min:0',
            'average_power_kw' => 'sometimes|numeric|min:0',
            'connector_used' => 'sometimes|string|max:50',

            // Payment and transaction
            'payment_method' => 'sometimes|in:credit_card,fleet_card,mobile_app,subscription,free',
            'payment_status' => 'sometimes|in:pending,completed,failed,refunded',
            'transaction_id' => 'sometimes|string|max:100',
            'invoice_number' => 'sometimes|string|max:100',

            // Environmental impact
            'co2_saved_kg' => 'sometimes|numeric|min:0',

            // Session quality
            'interruption_count' => 'sometimes|integer|min:0',
            'error_codes' => 'sometimes|string',

            // Location at start
            'start_latitude' => 'sometimes|numeric|between:-90,90',
            'start_longitude' => 'sometimes|numeric|between:-180,180',

            // Additional information
            'notes' => 'sometimes|string',
        ];
    }
}
