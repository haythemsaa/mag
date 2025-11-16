<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFuelTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('fuel-transactions.create');
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
            'driver_id' => 'nullable|exists:drivers,id',
            'transaction_date' => 'required|date',
            'transaction_time' => 'nullable|date_format:H:i:s',
            'fuel_type' => 'required|in:gasoline,diesel,electric,hybrid,lpg,cng',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
            'mileage' => 'nullable|integer|min:0',
            'fuel_card_number' => 'nullable|string|max:50',
            'station_name' => 'nullable|string|max:255',
            'station_location' => 'nullable|string|max:255',
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
            'transaction_date.required' => 'Transaction date is required',
            'fuel_type.required' => 'Fuel type is required',
            'fuel_type.in' => 'Invalid fuel type',
            'quantity.required' => 'Quantity is required',
            'quantity.min' => 'Quantity must be greater than 0',
            'unit_price.required' => 'Unit price is required',
            'unit_price.min' => 'Unit price must be positive',
            'total_cost.required' => 'Total cost is required',
            'total_cost.min' => 'Total cost must be positive',
            'mileage.min' => 'Mileage cannot be negative',
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

        // Auto-calculate total_cost if quantity and unit_price are provided
        if ($this->has('quantity') && $this->has('unit_price') && !$this->has('total_cost')) {
            $quantity = (float) $this->input('quantity');
            $unitPrice = (float) $this->input('unit_price');

            $this->merge([
                'total_cost' => round($quantity * $unitPrice, 2),
            ]);
        }

        // Set validated to false by default
        if (!$this->has('validated')) {
            $this->merge([
                'validated' => false,
            ]);
        }

        // Set anomaly_detected to false by default
        if (!$this->has('anomaly_detected')) {
            $this->merge([
                'anomaly_detected' => false,
            ]);
        }

        // Set transaction time to current time if not provided
        if (!$this->has('transaction_time') && $this->has('transaction_date')) {
            $this->merge([
                'transaction_time' => now()->format('H:i:s'),
            ]);
        }
    }

    /**
     * Additional validation after the default validation passes.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verify that total_cost matches quantity * unit_price (allow 1% tolerance for rounding)
            if ($this->has('quantity') && $this->has('unit_price') && $this->has('total_cost')) {
                $expectedCost = (float) $this->input('quantity') * (float) $this->input('unit_price');
                $actualCost = (float) $this->input('total_cost');

                $tolerance = $expectedCost * 0.01; // 1% tolerance
                if (abs($expectedCost - $actualCost) > max($tolerance, 0.01)) {
                    $validator->errors()->add(
                        'total_cost',
                        'Total cost does not match quantity x unit price'
                    );
                }
            }
        });
    }
}
