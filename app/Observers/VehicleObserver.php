<?php

namespace App\Observers;

use App\Models\Vehicle;

class VehicleObserver
{
    use BaseObserver;

    /**
     * Handle the Vehicle "created" event.
     */
    public function created(Vehicle $vehicle): void
    {
        $this->logActivity($vehicle, 'created');
    }

    /**
     * Handle the Vehicle "updated" event.
     */
    public function updated(Vehicle $vehicle): void
    {
        $this->logActivity($vehicle, 'updated');
    }

    /**
     * Handle the Vehicle "deleted" event.
     */
    public function deleted(Vehicle $vehicle): void
    {
        $this->logActivity($vehicle, 'deleted');
    }

    /**
     * Handle the Vehicle "restored" event.
     */
    public function restored(Vehicle $vehicle): void
    {
        $this->logActivity($vehicle, 'restored');
    }
}
