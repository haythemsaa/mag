<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendContractExpiryAlerts implements ShouldQueue
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
     * Sends notifications for contracts expiring in the next 30 days.
     */
    public function handle(): void
    {
        Log::info('Starting contract expiry alerts job', ['organization_id' => $this->organizationId]);

        // Get active contracts expiring in the next 30 days
        $query = Contract::with(['vehicle', 'organization'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)]);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        $contracts = $query->get();

        Log::info("Found {$contracts->count()} expiring contracts");

        foreach ($contracts as $contract) {
            $daysUntilExpiry = (int) now()->diffInDays($contract->end_date, false);

            // Only send notifications at specific intervals: 30, 14, 7, 3, 1, and 0 days
            if (!in_array($daysUntilExpiry, [30, 14, 7, 3, 1, 0])) {
                continue;
            }

            // Get users from the contract's organization with appropriate roles
            $users = User::where('organization_id', $contract->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', [
                        'super-admin',
                        'organization-admin',
                        'accountant'
                    ]);
                })
                ->get();

            foreach ($users as $user) {
                $user->notify(new ContractExpiringNotification($contract, $daysUntilExpiry));
            }

            Log::info("Sent contract expiry alert", [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'vehicle' => $contract->vehicle->registration_number,
                'days_until_expiry' => $daysUntilExpiry,
                'notified_users' => $users->count()
            ]);
        }

        Log::info('Contract expiry alerts job completed');
    }
}

