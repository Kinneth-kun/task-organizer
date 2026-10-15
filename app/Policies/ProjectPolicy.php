<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Administrators pass every check (Gate::before in AppServiceProvider).
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Interns work inside projects but never start them. */
    public function create(User $user): bool
    {
        return ! $user->isIntern();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasFullAccess()
            || $project->owner_id === $user->id
            || $project->created_by === $user->id
            || $project->isMember($user)
            || $user->managesDepartment($project->department_id);
    }

    /** Edit details, mark completed / reopen. */
    public function update(User $user, Project $project): bool
    {
        return $user->hasFullAccess()
            || $project->isProjectManager($user)
            || $user->managesDepartment($project->department_id);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** Only the owner (and administrators/executives) delete a project. */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasFullAccess() || $project->owner_id === $user->id;
    }

    public function createTask(User $user, Project $project): bool
    {
        return ! $user->isIntern() && $this->view($user, $project);
    }

    public function suggest(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}
