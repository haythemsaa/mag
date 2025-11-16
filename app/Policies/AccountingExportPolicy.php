<?php

namespace App\Policies;

use App\Models\AccountingExport;
use App\Models\User;

class AccountingExportPolicy
{
    /**
     * Determine if the user can view any exports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the export.
     */
    public function view(User $user, AccountingExport $export): bool
    {
        return $user->organization_id === $export->organization_id;
    }

    /**
     * Determine if the user can create exports.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the export.
     */
    public function update(User $user, AccountingExport $export): bool
    {
        return $user->organization_id === $export->organization_id;
    }

    /**
     * Determine if the user can delete the export.
     */
    public function delete(User $user, AccountingExport $export): bool
    {
        return $user->organization_id === $export->organization_id;
    }
}
