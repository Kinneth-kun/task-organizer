<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Departments are managed by administrators only; they pass through Gate::before.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Department $department): bool
    {
        return false;
    }

    public function delete(User $user, Department $department): bool
    {
        return false;
    }
}
