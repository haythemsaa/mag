<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTheftAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by controller/policy
        return $this->user()->can('update', $this->route('theftAlert'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'investigation_notes' => 'sometimes|string|max:2000',
            'resolution_notes' => 'sometimes|string|max:2000',
            'admin_notes' => 'sometimes|string|max:2000',
            'additional_data' => 'sometimes|array',
            'address' => 'sometimes|string|max:500',
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
            'investigation_notes.max' => 'Investigation notes cannot exceed 2000 characters',
            'resolution_notes.max' => 'Resolution notes cannot exceed 2000 characters',
            'admin_notes.max' => 'Admin notes cannot exceed 2000 characters',
            'address.max' => 'Address cannot exceed 500 characters',
        ];
    }
}
