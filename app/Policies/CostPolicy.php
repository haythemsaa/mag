<?php

namespace App\Policies;

use App\Models\Cost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CostPolicy
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
     * Determine whether the user can view any costs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('costs.view');
    }

    /**
     * Determine whether the user can view the cost.
     */
    public function view(User $user, Cost $cost): bool
    {
        return $user->can('costs.view')
            && $user->organization_id === $cost->organization_id;
    }

    /**
     * Determine whether the user can create costs.
     */
    public function create(User $user): bool
    {
        return $user->can('costs.create');
    }

    /**
     * Determine whether the user can update the cost.
     */
    public function update(User $user, Cost $cost): bool
    {
        // Can't update validated costs unless accountant or admin
        if ($cost->validated && !$user->hasAnyRole(['accountant', 'organization-admin'])) {
            return false;
        }

        return $user->can('costs.update')
            && $user->organization_id === $cost->organization_id;
    }

    /**
     * Determine whether the user can delete the cost.
     */
    public function delete(User $user, Cost $cost): bool
    {
        // Can't delete validated costs
        if ($cost->validated) {
            return false;
        }

        return $user->can('costs.delete')
            && $user->organization_id === $cost->organization_id;
    }

    /**
     * Determine whether the user can validate the cost.
     */
    public function validate(User $user, Cost $cost): bool
    {
        // Only accountants and admins can validate costs
        return $user->can('costs.validate')
            && $user->organization_id === $cost->organization_id
            && $user->hasAnyRole(['accountant', 'organization-admin']);
    }

    /**
     * Determine whether the user can view cost statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->can('costs.statistics');
    }

    /**
     * Determine whether the user can restore the cost.
     */
    public function restore(User $user, Cost $cost): bool
    {
        return $user->can('costs.delete')
            && $user->organization_id === $cost->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the cost.
     */
    public function forceDelete(User $user, Cost $cost): bool
    {
        return $user->hasRole('super-admin')
            && $user->organization_id === $cost->organization_id;
    }
}
