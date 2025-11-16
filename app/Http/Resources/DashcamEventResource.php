<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashcamEventResource extends JsonResource
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
            'event_number' => $this->event_number,
            'event_type' => $this->event_type,
            'event_description' => $this->getEventDescription(),
            'severity' => $this->severity,
            'severity_color' => $this->getSeverityColor(),
            'status' => $this->status,

            // Timestamp
            'event_timestamp' => $this->event_timestamp?->toIso8601String(),
            'event_age_days' => $this->getEventAge(),

            // Location
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'speed_kmh' => $this->speed_kmh,
            'speed_limit_kmh' => $this->speed_limit_kmh,
            'is_speeding' => $this->isSpeedingEvent(),
            'speeding_amount_kmh' => $this->getSpeedingAmount(),

            // G-Force metrics
            'g_force_x' => $this->g_force_x,
            'g_force_y' => $this->g_force_y,
            'g_force_z' => $this->g_force_z,
            'max_g_force' => $this->max_g_force,
            'is_critical_g_force' => $this->isCriticalGForce(),

            // Video
            'dashcam_provider' => $this->dashcam_provider,
            'external_event_id' => $this->external_event_id,
            'video_url' => $this->video_url,
            'video_thumbnail_url' => $this->video_thumbnail_url,
            'video_duration_seconds' => $this->video_duration_seconds,
            'video_available_until' => $this->video_available_until?->toIso8601String(),
            'has_video' => $this->hasVideo(),

            // Device
            'device_id' => $this->device_id,
            'device_serial' => $this->device_serial,
            'firmware_version' => $this->firmware_version,

            // Review
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'reviewer' => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
                'email' => $this->reviewer?->email,
            ],

            // Coaching
            'coaching_completed' => $this->coaching_completed,
            'coaching_completed_at' => $this->coaching_completed_at?->toIso8601String(),
            'coaching_notes' => $this->coaching_notes,
            'coach' => [
                'id' => $this->coach?->id,
                'name' => $this->coach?->name,
                'email' => $this->coach?->email,
            ],

            // Dispute
            'is_disputed' => $this->is_disputed,
            'dispute_reason' => $this->dispute_reason,
            'disputed_at' => $this->disputed_at?->toIso8601String(),

            // Status helpers
            'is_pending_review' => $this->isPendingReview(),
            'is_reviewed' => $this->isReviewed(),
            'is_acknowledged' => $this->isAcknowledged(),
            'requires_coaching' => $this->requiresCoaching(),
            'is_coaching_completed' => $this->isCoachingCompleted(),

            // Relationships
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
            ],
            'driver' => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'license_number' => $this->driver?->license_number,
            ],

            // Metadata
            'metadata' => $this->metadata,

            // Timestamps
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
