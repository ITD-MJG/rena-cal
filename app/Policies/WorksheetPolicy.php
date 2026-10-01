<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worksheet;

/**
 * Mirrors DevicePolicy: staff roles see every worksheet, a Hospital Admin only
 * sees the ones belonging to their own customer.
 */
class WorksheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any-worksheet') ||
               $user->hasRole(['Super Admin', 'Admin', 'Technician', 'Hospital Admin']);
    }

    public function view(User $user, Worksheet $worksheet): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin', 'Technician'])) {
            return true;
        }

        if ($user->hasRole('Hospital Admin')) {
            return $user->customer_id !== null
                && $user->customer_id === $worksheet->device?->customer_id;
        }

        return $user->can('view-worksheet');
    }

    public function create(User $user): bool
    {
        return $user->can('create-worksheet') ||
               $user->hasRole(['Super Admin', 'Admin', 'Technician']);
    }

    public function update(User $user, Worksheet $worksheet): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin', 'Technician'])) {
            return true;
        }

        return $user->can('update-worksheet');
    }

    public function delete(User $user, Worksheet $worksheet): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin', 'Technician'])) {
            return true;
        }

        return $user->can('delete-worksheet');
    }
}
