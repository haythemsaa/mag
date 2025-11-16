<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteStopRequest extends FormRequest
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
            'stop_number' => 'nullable|integer|min:1',
            'type' => 'required|string|in:pickup,delivery,service,visit,break',
            'location_name' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'service_duration_minutes' => 'nullable|integer|min:1|max:480',
            'time_window_start' => 'nullable|date_format:H:i',
            'time_window_end' => 'nullable|date_format:H:i|after:time_window_start',
            'instructions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'requires_signature' => 'nullable|boolean',
            'requires_photo' => 'nullable|boolean',
            'reference_number' => 'nullable|string|max:100',
            'package_count' => 'nullable|integer|min:0',
            'package_weight_kg' => 'nullable|numeric|min:0|max:100000',
            'package_volume_m3' => 'nullable|numeric|min:0|max:1000',
            'priority' => 'nullable|integer|min:1|max:5',
        ];
    }
}
