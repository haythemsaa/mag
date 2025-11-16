<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGeofenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-inject organization_id from authenticated user
        if (!$this->has('organization_id') && $this->user()) {
            $this->merge([
                'organization_id' => $this->user()->organization_id,
            ]);
        }
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:authorized,forbidden,client_site,depot,parking,service_area,delivery_zone,restricted',
            'shape' => 'required|in:circle,polygon',

            // Circle properties (required if shape is circle)
            'center_latitude' => 'required_if:shape,circle|nullable|numeric|between:-90,90',
            'center_longitude' => 'required_if:shape,circle|nullable|numeric|between:-180,180',
            'radius_meters' => 'required_if:shape,circle|nullable|integer|min:10|max:100000',

            // Polygon properties (required if shape is polygon)
            'polygon_coordinates' => 'required_if:shape,polygon|nullable|array|min:3',
            'polygon_coordinates.*.lat' => 'required|numeric|between:-90,90',
            'polygon_coordinates.*.lng' => 'required|numeric|between:-180,180',

            // Alert settings
            'alert_on_entry' => 'nullable|boolean',
            'alert_on_exit' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',

            // Time restrictions
            'active_from_time' => 'nullable|date_format:H:i:s',
            'active_to_time' => 'nullable|date_format:H:i:s|after:active_from_time',
            'active_days' => 'nullable|array',
            'active_days.*' => 'integer|between:1,7',

            // Address info
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shape.required' => 'The shape field is required (circle or polygon).',
            'shape.in' => 'The shape must be either circle or polygon.',
            'center_latitude.required_if' => 'Center latitude is required for circle geofences.',
            'center_longitude.required_if' => 'Center longitude is required for circle geofences.',
            'radius_meters.required_if' => 'Radius is required for circle geofences.',
            'polygon_coordinates.required_if' => 'Polygon coordinates are required for polygon geofences.',
            'polygon_coordinates.min' => 'A polygon must have at least 3 points.',
            'active_to_time.after' => 'End time must be after start time.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #3B82F6).',
        ];
    }
}
