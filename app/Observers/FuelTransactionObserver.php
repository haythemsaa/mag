<?php

namespace App\Observers;

use App\Models\FuelTransaction;

class FuelTransactionObserver
{
    use BaseObserver;

    /**
     * Handle the FuelTransaction "created" event.
     */
    public function created(FuelTransaction $fuelTransaction): void
    {
        $this->logActivity($fuelTransaction, 'created');
    }

    /**
     * Handle the FuelTransaction "updated" event.
     */
    public function updated(FuelTransaction $fuelTransaction): void
    {
        $this->logActivity($fuelTransaction, 'updated');
    }

    /**
     * Handle the FuelTransaction "deleted" event.
     */
    public function deleted(FuelTransaction $fuelTransaction): void
    {
        $this->logActivity($fuelTransaction, 'deleted');
    }

    /**
     * Handle the FuelTransaction "restored" event.
     */
    public function restored(FuelTransaction $fuelTransaction): void
    {
        $this->logActivity($fuelTransaction, 'restored');
    }
}
