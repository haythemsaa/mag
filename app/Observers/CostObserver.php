<?php

namespace App\Observers;

use App\Models\Cost;

class CostObserver
{
    use BaseObserver;

    /**
     * Handle the Cost "created" event.
     */
    public function created(Cost $cost): void
    {
        $this->logActivity($cost, 'created');
    }

    /**
     * Handle the Cost "updated" event.
     */
    public function updated(Cost $cost): void
    {
        $this->logActivity($cost, 'updated');
    }

    /**
     * Handle the Cost "deleted" event.
     */
    public function deleted(Cost $cost): void
    {
        $this->logActivity($cost, 'deleted');
    }

    /**
     * Handle the Cost "restored" event.
     */
    public function restored(Cost $cost): void
    {
        $this->logActivity($cost, 'restored');
    }
}
