<?php

namespace App\Policies;

use App\Models\TheftAlert;
use App\Models\User;

class TheftAlertPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view alerts from their organization
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TheftAlert $theftAlert): bool
    {
        return $user->organization_id === $theftAlert->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create alerts (typically via detection system)
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TheftAlert $theftAlert): bool
    {
        return $user->organization_id === $theftAlert->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TheftAlert $theftAlert): bool
    {
        return $user->organization_id === $theftAlert->organization_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TheftAlert $theftAlert): bool
    {
        return $user->organization_id === $theftAlert->organization_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TheftAlert $theftAlert): bool
    {
        return $user->organization_id === $theftAlert->organization_id;
    }
}
