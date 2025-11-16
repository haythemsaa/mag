<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteStopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('route'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:1000',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'contact_name' => 'sometimes|string|max:255',
            'contact_phone' => 'sometimes|string|max:50',
            'contact_email' => 'sometimes|email|max:255',
            'service_duration_minutes' => 'sometimes|integer|min:1|max:480',
            'time_window_start' => 'sometimes|date_format:H:i',
            'time_window_end' => 'sometimes|date_format:H:i',
            'instructions' => 'sometimes|string|max:2000',
            'notes' => 'sometimes|string|max:2000',
            'requires_signature' => 'sometimes|boolean',
            'requires_photo' => 'sometimes|boolean',
            'reference_number' => 'sometimes|string|max:100',
            'package_count' => 'sometimes|integer|min:0',
            'package_weight_kg' => 'sometimes|numeric|min:0|max:100000',
            'package_volume_m3' => 'sometimes|numeric|min:0|max:1000',
            'priority' => 'sometimes|integer|min:1|max:5',
        ];
    }
}
