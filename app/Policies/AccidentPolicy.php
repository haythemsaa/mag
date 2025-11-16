<?php

namespace App\Policies;

use App\Models\Accident;
use App\Models\User;

class AccidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accidents.view');
    }

    public function view(User $user, Accident $accident): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('accidents.view')
            && $user->organization_id === $accident->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('accidents.create');
    }

    public function update(User $user, Accident $accident): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('accidents.update')
            && $user->organization_id === $accident->organization_id;
    }

    public function delete(User $user, Accident $accident): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('accidents.delete')
            && $user->organization_id === $accident->organization_id;
    }
}
