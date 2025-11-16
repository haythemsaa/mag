<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountingExportRequest;
use App\Http\Resources\AccountingExportResource;
use App\Models\AccountingExport;
use App\Services\AccountingExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Accounting Exports
 *
 * APIs for managing accounting exports
 */
class AccountingExportController extends Controller
{
    public function __construct(protected AccountingExportService $exportService)
    {
    }

    /**
     * List accounting exports
     *
     * Get a paginated list of accounting exports for the authenticated user's organization.
     *
     * @queryParam status string Filter by status (pending, processing, completed, failed). Example: completed
     * @queryParam type string Filter by export type (costs, fuel, maintenance, contracts, all). Example: costs
     * @queryParam format string Filter by format (csv, excel, json, xml). Example: csv
     * @queryParam start_date date Filter exports created after this date. Example: 2025-01-01
     * @queryParam end_date date Filter exports created before this date. Example: 2025-12-31
     * @queryParam per_page int Number of items per page (default: 15). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountingExport::where('organization_id', $request->user()->organization_id)
            ->with(['user'])
            ->latest();

        // Apply filters
        if ($request->has('status')) {
            $query->withStatus($request->status);
        }

        if ($request->has('type')) {
            $query->withType($request->type);
        }

        if ($request->has('format')) {
            $query->withFormat($request->format);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        }

        $perPage = $request->input('per_page', 15);
        $exports = $query->paginate($perPage);

        return response()->json([
            'data' => AccountingExportResource::collection($exports->items()),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'total' => $exports->total(),
                'per_page' => $exports->perPage(),
                'last_page' => $exports->lastPage(),
            ],
        ]);
    }

    /**
     * Create accounting export
     *
     * Create a new accounting export request. The export will be queued for processing.
     *
     * @bodyParam type string required Export type (costs, fuel, maintenance, contracts, all, custom). Example: costs
     * @bodyParam format string required Export format (csv, excel, json, xml). Example: csv
     * @bodyParam start_date date required Start date for data export. Example: 2025-01-01
     * @bodyParam end_date date required End date for data export. Example: 2025-01-31
     * @bodyParam account_mapping object Account code mapping for different transaction types.
     * @bodyParam filters object Optional filters for custom exports (vehicle_ids, cost_types, etc.).
     */
    public function store(StoreAccountingExportRequest $request): JsonResponse
    {
        $this->authorize('create', AccountingExport::class);

        $export = AccountingExport::create([
            'organization_id' => $request->user()->organization_id,
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'format' => $request->format,
            'status' => 'pending',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'account_mapping' => $request->account_mapping ?? AccountingExport::getDefaultAccountMapping(),
            'filters' => $request->filters,
        ]);

        // Process export immediately (can be queued in production)
        try {
            $this->exportService->process($export);
        } catch (\Exception $e) {
            // Export is already marked as failed in the service
        }

        return response()->json([
            'message' => 'Export created successfully',
            'data' => new AccountingExportResource($export->fresh()),
        ], 201);
    }

    /**
     * Get export details
     *
     * Retrieve detailed information about a specific accounting export.
     *
     * @urlParam export int required The export ID. Example: 1
     */
    public function show(Request $request, AccountingExport $export): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json([
            'data' => new AccountingExportResource($export->load(['user'])),
        ]);
    }

    /**
     * Delete export
     *
     * Soft delete an accounting export and its associated file.
     *
     * @urlParam export int required The export ID. Example: 1
     */
    public function destroy(Request $request, AccountingExport $export): JsonResponse
    {
        $this->authorize('delete', $export);

        $export->delete();

        return response()->json([
            'message' => 'Export deleted successfully',
        ]);
    }

    /**
     * Download export file
     *
     * Download the generated export file.
     *
     * @urlParam export int required The export ID. Example: 1
     */
    public function download(Request $request, AccountingExport $export): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $export);

        if (! $export->canBeDownloaded()) {
            return response()->json([
                'message' => 'Export file is not available for download',
                'errors' => [
                    'status' => $export->status,
                    'file_exists' => $export->file_path && Storage::disk('local')->exists($export->file_path),
                ],
            ], 400);
        }

        // Track download
        $export->trackDownload($request->user()->email);

        // Get file path
        $filePath = Storage::disk('local')->path($export->file_path);
        $filename = basename($export->file_path);

        // Determine MIME type
        $mimeType = match ($export->format) {
            'csv' => 'text/csv',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };

        return response()->download($filePath, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Retry failed export
     *
     * Retry processing a failed export.
     *
     * @urlParam export int required The export ID. Example: 1
     */
    public function retry(Request $request, AccountingExport $export): JsonResponse
    {
        $this->authorize('update', $export);

        if (! $export->isFailed()) {
            return response()->json([
                'message' => 'Only failed exports can be retried',
                'current_status' => $export->status,
            ], 400);
        }

        // Reset export status
        $export->update([
            'status' => 'pending',
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_seconds' => null,
        ]);

        // Process export
        try {
            $this->exportService->process($export);
        } catch (\Exception $e) {
            // Export is already marked as failed
        }

        return response()->json([
            'message' => 'Export retry initiated',
            'data' => new AccountingExportResource($export->fresh()),
        ]);
    }

    /**
     * Get export statistics
     *
     * Get statistics about accounting exports for the organization.
     *
     * @queryParam start_date date Filter statistics from this date. Example: 2025-01-01
     * @queryParam end_date date Filter statistics until this date. Example: 2025-12-31
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = AccountingExport::where('organization_id', $request->user()->organization_id);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $exports = $query->get();

        $statistics = [
            'total_exports' => $exports->count(),
            'by_status' => [
                'pending' => $exports->where('status', 'pending')->count(),
                'processing' => $exports->where('status', 'processing')->count(),
                'completed' => $exports->where('status', 'completed')->count(),
                'failed' => $exports->where('status', 'failed')->count(),
            ],
            'by_type' => [
                'costs' => $exports->where('type', 'costs')->count(),
                'fuel' => $exports->where('type', 'fuel')->count(),
                'maintenance' => $exports->where('type', 'maintenance')->count(),
                'contracts' => $exports->where('type', 'contracts')->count(),
                'all' => $exports->where('type', 'all')->count(),
                'custom' => $exports->where('type', 'custom')->count(),
            ],
            'by_format' => [
                'csv' => $exports->where('format', 'csv')->count(),
                'excel' => $exports->where('format', 'excel')->count(),
                'json' => $exports->where('format', 'json')->count(),
                'xml' => $exports->where('format', 'xml')->count(),
            ],
            'total_records_exported' => $exports->sum('records_exported'),
            'total_file_size_bytes' => $exports->sum('file_size_bytes'),
            'total_downloads' => $exports->sum('download_count'),
            'average_processing_time_seconds' => round($exports->avg('processing_time_seconds'), 2),
            'success_rate' => $exports->count() > 0
                ? round(($exports->where('status', 'completed')->count() / $exports->count()) * 100, 2)
                : 0,
            'most_downloaded' => $exports->sortByDesc('download_count')
                ->take(5)
                ->map(fn ($export) => [
                    'export_number' => $export->export_number,
                    'type' => $export->type,
                    'format' => $export->format,
                    'download_count' => $export->download_count,
                ])
                ->values(),
        ];

        return response()->json($statistics);
    }

    /**
     * Get default account mapping
     *
     * Retrieve the default account code mapping.
     */
    public function defaultAccountMapping(): JsonResponse
    {
        return response()->json([
            'account_mapping' => AccountingExport::getDefaultAccountMapping(),
        ]);
    }

    /**
     * Preview export data
     *
     * Preview the data that would be exported without creating an actual export.
     *
     * @bodyParam type string required Export type. Example: costs
     * @bodyParam start_date date required Start date. Example: 2025-01-01
     * @bodyParam end_date date required End date. Example: 2025-01-31
     * @bodyParam filters object Optional filters.
     * @queryParam limit int Number of records to preview (default: 10). Example: 20
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:costs,fuel,maintenance,contracts,all,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'filters' => 'nullable|array',
        ]);

        // Create temporary export object (not saved to database)
        $tempExport = new AccountingExport([
            'organization_id' => $request->user()->organization_id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'filters' => $validated['filters'] ?? null,
            'account_mapping' => AccountingExport::getDefaultAccountMapping(),
        ]);

        // Get preview data
        $data = $this->exportService->getData($tempExport);
        $formatted = $this->exportService->formatData($data, $tempExport);

        $limit = $request->input('limit', 10);
        $preview = $formatted->take($limit);

        return response()->json([
            'preview' => $preview->values(),
            'total_records' => $data->count(),
            'showing' => $preview->count(),
            'date_range' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ],
        ]);
    }
}
