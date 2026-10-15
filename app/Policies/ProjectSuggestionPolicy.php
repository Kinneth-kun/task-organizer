<?php

namespace App\Policies;

use App\Models\ProjectSuggestion;
use App\Models\User;

class ProjectSuggestionPolicy
{
    /** The author, the project's owner and managers, and administrators/executives. */
    public function delete(User $user, ProjectSuggestion $suggestion): bool
    {
        return $suggestion->user_id === $user->id
            || ($suggestion->project !== null && $user->can('update', $suggestion->project));
    }
}
