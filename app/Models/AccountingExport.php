<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class AccountingExport extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'export_number',
        'type',
        'format',
        'status',
        'start_date',
        'end_date',
        'account_mapping',
        'filters',
        'file_path',
        'records_exported',
        'file_size_bytes',
        'started_at',
        'completed_at',
        'processing_time_seconds',
        'error_message',
        'downloaded_by',
        'downloaded_at',
        'download_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'account_mapping' => 'array',
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AccountingExport $export) {
            if (empty($export->export_number)) {
                $export->export_number = static::generateExportNumber();
            }
        });

        // Clean up file when export is deleted
        static::deleting(function (AccountingExport $export) {
            if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                Storage::disk('local')->delete($export->file_path);
            }
        });
    }

    /**
     * Generate unique export number: EXP-YYYY-NNNNNN
     */
    public static function generateExportNumber(): string
    {
        $year = now()->year;
        $lastExport = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastExport
            ? ((int) substr($lastExport->export_number, -6)) + 1
            : 1;

        return 'EXP-'.$year.'-'.str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the organization that owns the export.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope exports by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope exports by type
     */
    public function scopeWithType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope exports by format
     */
    public function scopeWithFormat($query, string $format)
    {
        return $query->where('format', $format);
    }

    /**
     * Scope exports within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate])
            ->orWhereBetween('end_date', [$startDate, $endDate]);
    }

    /**
     * Scope pending exports
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope processing exports
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope completed exports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope failed exports
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark export as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark export as completed
     */
    public function markAsCompleted(string $filePath, int $recordsExported, int $fileSizeBytes): void
    {
        $processingTime = $this->started_at
            ? now()->diffInSeconds($this->started_at)
            : null;

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'file_path' => $filePath,
            'records_exported' => $recordsExported,
            'file_size_bytes' => $fileSizeBytes,
            'processing_time_seconds' => $processingTime,
            'error_message' => null,
        ]);
    }

    /**
     * Mark export as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $processingTime = $this->started_at
            ? now()->diffInSeconds($this->started_at)
            : null;

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'processing_time_seconds' => $processingTime,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Track download
     */
    public function trackDownload(string $userEmail): void
    {
        $this->update([
            'downloaded_by' => $userEmail,
            'downloaded_at' => now(),
            'download_count' => $this->download_count + 1,
        ]);
    }

    /**
     * Check if export is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if export is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if export is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if export is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if export can be downloaded
     */
    public function canBeDownloaded(): bool
    {
        return $this->isCompleted() &&
            $this->file_path &&
            Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeFormatted(): ?string
    {
        if (! $this->file_size_bytes) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size_bytes;
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2).' '.$units[$unit];
    }

    /**
     * Get processing time in human readable format
     */
    public function getProcessingTimeFormatted(): ?string
    {
        if (! $this->processing_time_seconds) {
            return null;
        }

        $seconds = $this->processing_time_seconds;

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes.'m '.$remainingSeconds.'s';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours.'h '.$remainingMinutes.'m '.$remainingSeconds.'s';
    }

    /**
     * Get default account mapping
     */
    public static function getDefaultAccountMapping(): array
    {
        return [
            'fuel_account' => '6061', // Fuel purchases
            'maintenance_account' => '6155', // Maintenance and repairs
            'insurance_account' => '6162', // Insurance premiums
            'tax_account' => '6351', // Vehicle taxes
            'toll_account' => '6236', // Tolls and parking
            'tire_account' => '6155', // Tires (same as maintenance)
            'depreciation_account' => '6811', // Depreciation
            'lease_account' => '6132', // Lease/rental
            'fine_account' => '6712', // Fines and penalties
        ];
    }
}
