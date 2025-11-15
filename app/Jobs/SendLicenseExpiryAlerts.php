<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Models\User;
use App\Notifications\LicenseExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLicenseExpiryAlerts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $organizationId = null
    ) {}

    /**
     * Execute the job.
     *
     * Sends notifications for driver licenses expiring in the next 60 days.
     */
    public function handle(): void
    {
        Log::info('Starting license expiry alerts job', ['organization_id' => $this->organizationId]);

        // Get active drivers with licenses expiring in the next 60 days
        $query = Driver::with(['organization'])
            ->where('status', 'active')
            ->whereNotNull('license_expiry_date')
            ->whereBetween('license_expiry_date', [now(), now()->addDays(60)]);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        $drivers = $query->get();

        Log::info("Found {$drivers->count()} drivers with expiring licenses");

        foreach ($drivers as $driver) {
            $daysUntilExpiry = (int) now()->diffInDays($driver->license_expiry_date, false);

            // Send notifications at: 60, 30, 14, 7, 3, 1, and 0 days (more frequent as it's critical)
            if (!in_array($daysUntilExpiry, [60, 30, 14, 7, 3, 1, 0])) {
                continue;
            }

            // Get users from the driver's organization with appropriate roles
            $users = User::where('organization_id', $driver->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', [
                        'super-admin',
                        'organization-admin',
                        'fleet-manager'
                    ]);
                })
                ->get();

            foreach ($users as $user) {
                $user->notify(new LicenseExpiringNotification($driver, $daysUntilExpiry));
            }

            Log::info("Sent license expiry alert", [
                'driver_id' => $driver->id,
                'driver_name' => $driver->first_name . ' ' . $driver->last_name,
                'license_number' => $driver->license_number,
                'days_until_expiry' => $daysUntilExpiry,
                'notified_users' => $users->count()
            ]);
        }

        Log::info('License expiry alerts job completed');
    }
}

