<?php

namespace App\Policies;

use App\Models\ChargingStation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChargingStationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_charging_stations');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChargingStation $chargingStation): bool
    {
        return $user->hasPermissionTo('view_charging_stations')
            && $user->organization_id === $chargingStation->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_charging_stations');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChargingStation $chargingStation): bool
    {
        return $user->hasPermissionTo('update_charging_stations')
            && $user->organization_id === $chargingStation->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChargingStation $chargingStation): bool
    {
        return $user->hasPermissionTo('delete_charging_stations')
            && $user->organization_id === $chargingStation->organization_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChargingStation $chargingStation): bool
    {
        return $user->hasPermissionTo('delete_charging_stations')
            && $user->organization_id === $chargingStation->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChargingStation $chargingStation): bool
    {
        return $user->hasPermissionTo('delete_charging_stations')
            && $user->organization_id === $chargingStation->organization_id;
    }
}
