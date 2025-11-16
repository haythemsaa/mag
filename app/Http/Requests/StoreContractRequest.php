<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('contracts.create');
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
            'contract_type' => 'required|in:lease,insurance,maintenance,full_service,other',
            'contract_number' => 'required|string|max:100|unique:contracts,contract_number',
            'supplier' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'monthly_cost' => 'nullable|numeric|min:0',
            'mileage_limit' => 'nullable|integer|min:0',
            'excess_mileage_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,expired,cancelled,pending',
            'auto_renewal' => 'nullable|boolean',
            'notice_period_days' => 'nullable|integer|min:0|max:365',
            'terms' => 'nullable|string|max:2000',
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
            'vehicle_id.required' => 'Vehicle is required',
            'vehicle_id.exists' => 'Selected vehicle does not exist',
            'contract_type.required' => 'Contract type is required',
            'contract_type.in' => 'Invalid contract type',
            'contract_number.required' => 'Contract number is required',
            'contract_number.unique' => 'This contract number is already in use',
            'supplier.required' => 'Supplier is required',
            'start_date.required' => 'Start date is required',
            'end_date.required' => 'End date is required',
            'end_date.after' => 'End date must be after start date',
            'monthly_cost.min' => 'Monthly cost must be positive',
            'mileage_limit.min' => 'Mileage limit cannot be negative',
            'excess_mileage_cost.min' => 'Excess mileage cost must be positive',
            'status.in' => 'Invalid status',
            'notice_period_days.max' => 'Notice period cannot exceed 365 days',
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

        // Set default status based on dates
        if (!$this->has('status')) {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            $status = 'pending';
            if ($startDate && $endDate) {
                $now = now();
                $start = \Carbon\Carbon::parse($startDate);
                $end = \Carbon\Carbon::parse($endDate);

                if ($now->greaterThan($end)) {
                    $status = 'expired';
                } elseif ($now->between($start, $end)) {
                    $status = 'active';
                }
            }

            $this->merge([
                'status' => $status,
            ]);
        }

        // Set auto_renewal to false by default
        if (!$this->has('auto_renewal')) {
            $this->merge([
                'auto_renewal' => false,
            ]);
        }

        // Set default notice period based on contract type
        if (!$this->has('notice_period_days')) {
            $contractType = $this->input('contract_type');
            $defaultNoticePeriods = [
                'lease' => 90,
                'insurance' => 30,
                'maintenance' => 30,
                'full_service' => 60,
                'other' => 30,
            ];

            $this->merge([
                'notice_period_days' => $defaultNoticePeriods[$contractType] ?? 30,
            ]);
        }
    }
}
