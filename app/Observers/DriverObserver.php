<?php

namespace App\Observers;

use App\Models\Driver;

class DriverObserver
{
    use BaseObserver;

    /**
     * Handle the Driver "created" event.
     */
    public function created(Driver $driver): void
    {
        $this->logActivity($driver, 'created');
    }

    /**
     * Handle the Driver "updated" event.
     */
    public function updated(Driver $driver): void
    {
        $this->logActivity($driver, 'updated');
    }

    /**
     * Handle the Driver "deleted" event.
     */
    public function deleted(Driver $driver): void
    {
        $this->logActivity($driver, 'deleted');
    }

    /**
     * Handle the Driver "restored" event.
     */
    public function restored(Driver $driver): void
    {
        $this->logActivity($driver, 'restored');
    }
}
