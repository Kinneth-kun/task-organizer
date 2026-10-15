<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management is for administrators only; they pass through Gate::before.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }
}
