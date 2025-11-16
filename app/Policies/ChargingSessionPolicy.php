<?php

namespace App\Policies;

use App\Models\ChargingSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChargingSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_charging_sessions');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChargingSession $chargingSession): bool
    {
        return $user->hasPermissionTo('view_charging_sessions')
            && $user->organization_id === $chargingSession->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_charging_sessions');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChargingSession $chargingSession): bool
    {
        return $user->hasPermissionTo('update_charging_sessions')
            && $user->organization_id === $chargingSession->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChargingSession $chargingSession): bool
    {
        return $user->hasPermissionTo('delete_charging_sessions')
            && $user->organization_id === $chargingSession->organization_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChargingSession $chargingSession): bool
    {
        return $user->hasPermissionTo('delete_charging_sessions')
            && $user->organization_id === $chargingSession->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChargingSession $chargingSession): bool
    {
        return $user->hasPermissionTo('delete_charging_sessions')
            && $user->organization_id === $chargingSession->organization_id;
    }
}
