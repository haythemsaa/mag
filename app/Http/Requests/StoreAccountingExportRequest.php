<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['costs', 'fuel', 'maintenance', 'contracts', 'all', 'custom']),
            ],
            'format' => [
                'required',
                'string',
                Rule::in(['csv', 'excel', 'json', 'xml']),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'account_mapping' => 'nullable|array',
            'account_mapping.fuel_account' => 'nullable|string|max:20',
            'account_mapping.maintenance_account' => 'nullable|string|max:20',
            'account_mapping.insurance_account' => 'nullable|string|max:20',
            'account_mapping.tax_account' => 'nullable|string|max:20',
            'account_mapping.toll_account' => 'nullable|string|max:20',
            'account_mapping.tire_account' => 'nullable|string|max:20',
            'account_mapping.depreciation_account' => 'nullable|string|max:20',
            'account_mapping.lease_account' => 'nullable|string|max:20',
            'account_mapping.fine_account' => 'nullable|string|max:20',
            'filters' => 'nullable|array',
            'filters.vehicle_ids' => 'nullable|array',
            'filters.vehicle_ids.*' => [
                'integer',
                Rule::exists('vehicles', 'id')->where(function ($query) {
                    $query->where('organization_id', $this->user()->organization_id);
                }),
            ],
            'filters.cost_types' => 'nullable|array',
            'filters.cost_types.*' => 'string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Export type is required',
            'type.in' => 'Invalid export type. Must be one of: costs, fuel, maintenance, contracts, all, custom',
            'format.required' => 'Export format is required',
            'format.in' => 'Invalid export format. Must be one of: csv, excel, json, xml',
            'start_date.required' => 'Start date is required',
            'end_date.required' => 'End date is required',
            'end_date.after_or_equal' => 'End date must be equal to or after start date',
            'filters.vehicle_ids.*.exists' => 'One or more vehicle IDs do not exist or do not belong to your organization',
        ];
    }
}
