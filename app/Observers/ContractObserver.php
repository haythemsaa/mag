<?php

namespace App\Observers;

use App\Models\Contract;

class ContractObserver
{
    use BaseObserver;

    /**
     * Handle the Contract "created" event.
     */
    public function created(Contract $contract): void
    {
        $this->logActivity($contract, 'created');
    }

    /**
     * Handle the Contract "updated" event.
     */
    public function updated(Contract $contract): void
    {
        $this->logActivity($contract, 'updated');
    }

    /**
     * Handle the Contract "deleted" event.
     */
    public function deleted(Contract $contract): void
    {
        $this->logActivity($contract, 'deleted');
    }

    /**
     * Handle the Contract "restored" event.
     */
    public function restored(Contract $contract): void
    {
        $this->logActivity($contract, 'restored');
    }
}
