<?php

namespace App\Observers;

use App\Models\Maintenance;

class MaintenanceObserver
{
    use BaseObserver;

    /**
     * Handle the Maintenance "created" event.
     */
    public function created(Maintenance $maintenance): void
    {
        $this->logActivity($maintenance, 'created');
    }

    /**
     * Handle the Maintenance "updated" event.
     */
    public function updated(Maintenance $maintenance): void
    {
        $this->logActivity($maintenance, 'updated');
    }

    /**
     * Handle the Maintenance "deleted" event.
     */
    public function deleted(Maintenance $maintenance): void
    {
        $this->logActivity($maintenance, 'deleted');
    }

    /**
     * Handle the Maintenance "restored" event.
     */
    public function restored(Maintenance $maintenance): void
    {
        $this->logActivity($maintenance, 'restored');
    }
}
