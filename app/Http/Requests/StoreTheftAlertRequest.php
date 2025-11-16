<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTheftAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by controller/policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->organization_id);
                }),
            ],
            'geofence_id' => [
                'nullable',
                'integer',
                Rule::exists('geofences', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->organization_id);
                }),
            ],
            'type' => [
                'required',
                'string',
                Rule::in([
                    'movement_outside_hours',
                    'geofence_violation',
                    'gps_signal_loss',
                    'unauthorized_ignition',
                    'towing_detected',
                    'speed_anomaly',
                    'route_deviation',
                ]),
            ],
            'severity' => [
                'required',
                'string',
                Rule::in(['low', 'medium', 'high', 'critical']),
            ],
            'description' => 'required|string|max:2000',
            'detected_at' => 'nullable|date',

            // Location data
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',

            // Technical data
            'gps_signal_available' => 'nullable|boolean',
            'signal_strength' => 'nullable|integer|min:0|max:100',
            'speed_kmh' => 'nullable|numeric|min:0|max:300',
            'heading' => 'nullable|numeric|min:0|max:360',
            'engine_on' => 'nullable|boolean',
            'ignition_on' => 'nullable|boolean',

            // Alert triggers
            'outside_work_hours' => 'nullable|boolean',
            'geofence_exit_unauthorized' => 'nullable|boolean',
            'gps_jamming_suspected' => 'nullable|boolean',
            'towing_movement_detected' => 'nullable|boolean',
            'speed_anomaly_detected' => 'nullable|boolean',

            // Additional data
            'additional_data' => 'nullable|array',
            'admin_notes' => 'nullable|string|max:2000',
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
            'vehicle_id.required' => 'A vehicle must be specified for the alert',
            'vehicle_id.exists' => 'The selected vehicle does not exist or does not belong to your organization',
            'geofence_id.exists' => 'The selected geofence does not exist or does not belong to your organization',
            'type.required' => 'Alert type is required',
            'type.in' => 'Invalid alert type',
            'severity.required' => 'Alert severity is required',
            'severity.in' => 'Invalid alert severity level',
            'description.required' => 'Alert description is required',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'signal_strength.between' => 'Signal strength must be between 0 and 100',
            'speed_kmh.max' => 'Speed cannot exceed 300 km/h',
            'heading.between' => 'Heading must be between 0 and 360 degrees',
        ];
    }
}
