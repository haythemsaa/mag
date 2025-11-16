<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePredictionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prediction_number' => $this->prediction_number,

            // Prediction details
            'prediction_type' => $this->prediction_type,
            'type_label' => $this->getTypeLabel(),
            'title' => $this->title,
            'description' => $this->description,

            // Metadata
            'confidence' => $this->confidence,
            'confidence_color' => $this->getConfidenceColor(),
            'priority' => $this->priority,
            'priority_color' => $this->getPriorityColor(),
            'status' => $this->status,
            'urgency_level' => $this->getUrgencyLevel(),

            // Timeline
            'predicted_date' => $this->predicted_date?->toIso8601String(),
            'days_until_due' => $this->days_until_due,
            'recommended_action_by' => $this->recommended_action_by?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'is_due_soon' => $this->isDueSoon(),

            // Odometer
            'current_odometer_km' => $this->current_odometer_km,
            'predicted_odometer_km' => $this->predicted_odometer_km,
            'odometer_delta_km' => $this->predicted_odometer_km && $this->current_odometer_km
                ? $this->predicted_odometer_km - $this->current_odometer_km
                : null,

            // Cost estimates
            'estimated_cost_min' => $this->estimated_cost_min,
            'estimated_cost_max' => $this->estimated_cost_max,
            'estimated_cost_avg' => $this->estimated_cost_avg,
            'estimated_cost_range' => $this->getEstimatedCostRange(),

            // Algorithm
            'algorithm_used' => $this->algorithm_used,
            'algorithm_params' => $this->algorithm_params,
            'historical_data_summary' => $this->historical_data_summary,

            // Recommendations
            'recommended_actions' => $this->recommended_actions,
            'preventive_measures' => $this->preventive_measures,

            // Vehicle
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
                'vin' => $this->vehicle?->vin,
                'current_mileage_km' => $this->vehicle?->current_mileage_km,
            ],

            // Related records
            'related_maintenance' => $this->when($this->relatedMaintenance, [
                'id' => $this->relatedMaintenance?->id,
                'maintenance_number' => $this->relatedMaintenance?->maintenance_number,
                'maintenance_type' => $this->relatedMaintenance?->maintenance_type,
            ]),
            'related_contract' => $this->when($this->relatedContract, [
                'id' => $this->relatedContract?->id,
                'contract_number' => $this->relatedContract?->contract_number,
                'contract_type' => $this->relatedContract?->contract_type,
                'end_date' => $this->relatedContract?->end_date?->toIso8601String(),
            ]),

            // Acknowledgment
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $this->when($this->acknowledgedBy, [
                'id' => $this->acknowledgedBy?->id,
                'name' => $this->acknowledgedBy?->name,
                'email' => $this->acknowledgedBy?->email,
            ]),
            'acknowledgment_notes' => $this->acknowledgment_notes,

            // Scheduling
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'scheduled_maintenance' => $this->when($this->scheduledMaintenance, [
                'id' => $this->scheduledMaintenance?->id,
                'maintenance_number' => $this->scheduledMaintenance?->maintenance_number,
                'scheduled_date' => $this->scheduledMaintenance?->scheduled_date?->toIso8601String(),
            ]),

            // Completion/Dismissal
            'completed_at' => $this->completed_at?->toIso8601String(),
            'dismissed_at' => $this->dismissed_at?->toIso8601String(),
            'dismissed_by' => $this->when($this->dismissedBy, [
                'id' => $this->dismissedBy?->id,
                'name' => $this->dismissedBy?->name,
                'email' => $this->dismissedBy?->email,
            ]),
            'dismissal_reason' => $this->dismissal_reason,

            // Accuracy tracking
            'was_accurate' => $this->was_accurate,
            'accuracy_variance_days' => $this->accuracy_variance_days,
            'accuracy_notes' => $this->accuracy_notes,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
