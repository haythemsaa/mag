<?php

namespace App\Policies;

use App\Models\DashcamEvent;
use App\Models\User;

class DashcamEventPolicy
{
    /**
     * Determine if the user can view any events.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the event.
     */
    public function view(User $user, DashcamEvent $event): bool
    {
        return $user->organization_id === $event->organization_id;
    }

    /**
     * Determine if the user can update the event.
     */
    public function update(User $user, DashcamEvent $event): bool
    {
        return $user->organization_id === $event->organization_id;
    }

    /**
     * Determine if the user can delete the event.
     */
    public function delete(User $user, DashcamEvent $event): bool
    {
        return $user->organization_id === $event->organization_id;
    }
}
