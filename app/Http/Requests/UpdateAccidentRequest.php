<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accident = $this->route('accident');
        return $this->user()->can('accidents.update')
            && $this->user()->organization_id === $accident->organization_id;
    }

    public function rules(): array
    {
        return [
            'accident_date' => 'sometimes|date|before_or_equal:now',
            'location' => 'sometimes|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'severity' => 'sometimes|in:minor,moderate,severe,total_loss',
            'responsibility' => 'sometimes|in:driver,third_party,shared,unknown',
            'description' => 'sometimes|string',
            'police_report' => 'boolean',
            'police_report_number' => 'nullable|string|max:255',
            'injuries' => 'boolean',
            'injured_count' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:declared,in_progress,expertised,repaired,closed',
            'estimated_cost' => 'nullable|numeric|min:0',
            'final_cost' => 'nullable|numeric|min:0',
            'insurance_claim_number' => 'nullable|string|max:255',
            'insurance_status' => 'nullable|in:pending,accepted,rejected,partially_accepted',
            'notes' => 'nullable|string',
        ];
    }
}
