<?php

namespace App\Policies;

use App\Models\Infraction;
use App\Models\User;

class InfractionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('infractions.view');
    }

    public function view(User $user, Infraction $infraction): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('infractions.view')
            && $user->organization_id === $infraction->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('infractions.create');
    }

    public function update(User $user, Infraction $infraction): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('infractions.update')
            && $user->organization_id === $infraction->organization_id;
    }

    public function delete(User $user, Infraction $infraction): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('infractions.delete')
            && $user->organization_id === $infraction->organization_id;
    }
}
