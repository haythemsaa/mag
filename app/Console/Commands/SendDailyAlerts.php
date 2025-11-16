<?php

namespace App\Console\Commands;

use App\Jobs\SendContractExpiryAlerts;
use App\Jobs\SendLicenseExpiryAlerts;
use App\Jobs\SendMaintenanceReminders;
use Illuminate\Console\Command;

class SendDailyAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:send-daily
                            {--organization= : Send alerts for a specific organization only}
                            {--type=* : Alert types to send (maintenance, contract, license). Default: all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily alerts for maintenances, contracts, and licenses';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔔 Starting daily alerts dispatch...');

        $organizationId = $this->option('organization') ? (int) $this->option('organization') : null;
        $types = $this->option('type') ?: ['maintenance', 'contract', 'license'];

        if ($organizationId) {
            $this->info("  Filtering for organization ID: {$organizationId}");
        } else {
            $this->info('  Processing all organizations');
        }

        $jobsDispatched = 0;

        // Dispatch maintenance reminders
        if (in_array('maintenance', $types)) {
            $this->info('  📋 Dispatching maintenance reminders...');
            SendMaintenanceReminders::dispatch($organizationId);
            $jobsDispatched++;
        }

        // Dispatch contract expiry alerts
        if (in_array('contract', $types)) {
            $this->info('  📄 Dispatching contract expiry alerts...');
            SendContractExpiryAlerts::dispatch($organizationId);
            $jobsDispatched++;
        }

        // Dispatch license expiry alerts
        if (in_array('license', $types)) {
            $this->info('  🪪 Dispatching license expiry alerts...');
            SendLicenseExpiryAlerts::dispatch($organizationId);
            $jobsDispatched++;
        }

        $this->newLine();
        $this->info("✅ Successfully dispatched {$jobsDispatched} alert job(s)");
        $this->comment('   Jobs will be processed by the queue worker.');

        return Command::SUCCESS;
    }
}
