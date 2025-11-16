<?php

namespace App\Policies;

use App\Models\Geofence;
use App\Models\User;

class GeofencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('geofences.view');
    }

    public function view(User $user, Geofence $geofence): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('geofences.view')
            && $user->organization_id === $geofence->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('geofences.create');
    }

    public function update(User $user, Geofence $geofence): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('geofences.update')
            && $user->organization_id === $geofence->organization_id;
    }

    public function delete(User $user, Geofence $geofence): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('geofences.delete')
            && $user->organization_id === $geofence->organization_id;
    }
}
