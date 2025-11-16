<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingExportResource extends JsonResource
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
            'export_number' => $this->export_number,
            'type' => $this->type,
            'format' => $this->format,
            'status' => $this->status,

            // Date range
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'date_range_days' => $this->start_date && $this->end_date
                ? $this->start_date->diffInDays($this->end_date) + 1
                : null,

            // Configuration
            'account_mapping' => $this->account_mapping,
            'filters' => $this->filters,

            // Export results
            'file_path' => $this->file_path,
            'records_exported' => $this->records_exported,
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_formatted' => $this->getFileSizeFormatted(),

            // Processing metadata
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'processing_time_seconds' => $this->processing_time_seconds,
            'processing_time_formatted' => $this->getProcessingTimeFormatted(),

            // Error handling
            'error_message' => $this->error_message,

            // Download tracking
            'download_count' => $this->download_count,
            'downloaded_by' => $this->downloaded_by,
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),

            // Status helpers
            'can_be_downloaded' => $this->canBeDownloaded(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),

            // Relationships
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
