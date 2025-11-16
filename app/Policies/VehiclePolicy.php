<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;

class VehiclePolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Super admins can do anything
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null; // Continue to the specific ability checks
    }

    /**
     * Determine whether the user can view any vehicles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('vehicles.view');
    }

    /**
     * Determine whether the user can view the vehicle.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        // User must have permission and belong to the same organization
        return $user->can('vehicles.view')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can create vehicles.
     */
    public function create(User $user): bool
    {
        return $user->can('vehicles.create');
    }

    /**
     * Determine whether the user can update the vehicle.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can delete the vehicle.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can assign a driver to the vehicle.
     */
    public function assignDriver(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.assign-driver')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can update vehicle mileage.
     */
    public function updateMileage(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update-mileage')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can view vehicle statistics.
     */
    public function viewStatistics(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.statistics')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can restore the vehicle.
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete')
            && $user->organization_id === $vehicle->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the vehicle.
     */
    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole('super-admin')
            && $user->organization_id === $vehicle->organization_id;
    }
}
