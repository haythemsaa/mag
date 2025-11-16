<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'planned_date' => 'required|date',
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->organization_id);
                }),
            ],
            'driver_id' => [
                'nullable',
                'integer',
                Rule::exists('drivers', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->organization_id);
                }),
            ],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
