<?php

namespace App\Console\Commands;

use App\Models\AccountingExport;
use App\Services\AccountingExportService;
use Illuminate\Console\Command;

class ProcessAccountingExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:process-accounting
                            {--limit=10 : Maximum number of exports to process}
                            {--timeout=300 : Timeout in seconds for each export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending accounting exports';

    /**
     * Execute the console command.
     */
    public function handle(AccountingExportService $exportService): int
    {
        $limit = $this->option('limit');
        $timeout = $this->option('timeout');

        $this->info('Processing pending accounting exports...');

        $pendingExports = AccountingExport::pending()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($pendingExports->isEmpty()) {
            $this->info('No pending exports found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$pendingExports->count()} pending exports.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($pendingExports as $export) {
            $this->info("Processing export #{$export->id} ({$export->export_number})...");

            try {
                set_time_limit($timeout);

                $exportService->process($export);

                if ($export->fresh()->isCompleted()) {
                    $this->info("  ✓ Completed: {$export->records_exported} records exported");
                    $successCount++;
                } else {
                    $this->error("  ✗ Failed: {$export->fresh()->error_message}");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info("Processing complete:");
        $this->info("  Success: $successCount");
        $this->info("  Failed: $failureCount");

        return Command::SUCCESS;
    }
}
