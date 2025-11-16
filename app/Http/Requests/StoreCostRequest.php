<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('costs.create');
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
            'category' => 'required|in:fuel,maintenance,insurance,tax,parking,toll,fine,lease,depreciation,other',
            'subcategory' => 'nullable|string|max:100',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_deductible' => 'nullable|boolean',
            'date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,credit_card,bank_transfer,check,other',
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
            'category.required' => 'Cost category is required',
            'category.in' => 'Invalid cost category',
            'description.required' => 'Description is required',
            'description.max' => 'Description cannot exceed 500 characters',
            'amount.required' => 'Amount is required',
            'amount.min' => 'Amount must be positive',
            'date.required' => 'Date is required',
            'payment_method.in' => 'Invalid payment method',
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

        // Set validated to false by default (only accountants can validate)
        if (!$this->has('validated')) {
            $this->merge([
                'validated' => false,
            ]);
        }

        // Set vat_deductible to true by default
        if (!$this->has('vat_deductible')) {
            $this->merge([
                'vat_deductible' => true,
            ]);
        }

        // If VAT amount is not provided but we have amount, calculate default VAT (20%)
        if (!$this->has('vat_amount') && $this->has('amount')) {
            $amount = (float) $this->input('amount');
            $this->merge([
                'vat_amount' => round($amount * 0.20, 2),
            ]);
        }
    }
}
