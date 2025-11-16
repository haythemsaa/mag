<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('drivers.create');
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
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255|unique:drivers,email',
            'phone' => 'nullable|string|max:20',
            'license_number' => 'required|string|max:50|unique:drivers,license_number',
            'license_category' => 'nullable|string|max:10',
            'license_issue_date' => 'nullable|date|before:today',
            'license_expiry_date' => 'nullable|date|after:today',
            'date_of_birth' => 'nullable|date|before:today',
            'hire_date' => 'nullable|date',
            'employment_type' => 'nullable|in:full_time,part_time,contractor,temporary',
            'status' => 'nullable|in:active,inactive,on_leave,terminated',
            'eco_driving_score' => 'nullable|numeric|min:0|max:10',
            'total_infractions' => 'nullable|integer|min:0',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
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
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.unique' => 'This email is already in use',
            'license_number.required' => 'License number is required',
            'license_number.unique' => 'This license number is already registered',
            'license_expiry_date.after' => 'License must not be expired',
            'date_of_birth.before' => 'Birth date must be in the past',
            'employment_type.in' => 'Invalid employment type',
            'status.in' => 'Invalid status',
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

        // Set default eco_driving_score if not provided
        if (!$this->has('eco_driving_score')) {
            $this->merge([
                'eco_driving_score' => 5.0, // Neutral score
            ]);
        }

        // Set default total_infractions if not provided
        if (!$this->has('total_infractions')) {
            $this->merge([
                'total_infractions' => 0,
            ]);
        }
    }
}
