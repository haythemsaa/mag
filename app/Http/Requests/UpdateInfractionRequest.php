<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInfractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $infraction = $this->route('infraction');
        return $this->user()->can('infractions.update')
            && $this->user()->organization_id === $infraction->organization_id;
    }

    public function rules(): array
    {
        return [
            'driver_id' => 'nullable|exists:drivers,id',
            'reference_number' => 'nullable|string|max:255',
            'type' => 'sometimes|in:speeding,red_light,parking,phone,seatbelt,alcohol,dangerous_driving,stop_sign,wrong_way,other',
            'infraction_date' => 'sometimes|date|before_or_equal:today',
            'infraction_time' => 'nullable|date_format:H:i',
            'location' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:received,pending,assigned,contested,paid,cancelled',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
