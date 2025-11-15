<?php

namespace App\Jobs;

use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMaintenanceReminders implements ShouldQueue
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
     * Sends notifications for upcoming maintenances in the next 7 days.
     */
    public function handle(): void
    {
        Log::info('Starting maintenance reminder job', ['organization_id' => $this->organizationId]);

        // Get pending maintenances scheduled in the next 7 days
        $query = Maintenance::with(['vehicle', 'organization'])
            ->where('status', 'pending')
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [now(), now()->addDays(7)]);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        $maintenances = $query->get();

        Log::info("Found {$maintenances->count()} upcoming maintenances");

        foreach ($maintenances as $maintenance) {
            $daysUntilDue = (int) now()->diffInDays($maintenance->scheduled_date, false);

            // Get users from the maintenance's organization with appropriate roles
            $users = User::where('organization_id', $maintenance->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', [
                        'super-admin',
                        'organization-admin',
                        'fleet-manager',
                        'maintenance-manager'
                    ]);
                })
                ->get();

            foreach ($users as $user) {
                $user->notify(new MaintenanceDueNotification($maintenance, $daysUntilDue));
            }

            Log::info("Sent maintenance reminder", [
                'maintenance_id' => $maintenance->id,
                'vehicle' => $maintenance->vehicle->registration_number,
                'days_until_due' => $daysUntilDue,
                'notified_users' => $users->count()
            ]);
        }

        Log::info('Maintenance reminder job completed');
    }
}

