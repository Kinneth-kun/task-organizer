<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Who may see and change a task. Administrators pass every check (Gate::before).
 *
 * Executives see every task but only change the ones they are personally on. Project roles reach
 * only the tasks of the user's own department; department supervisors reach all of their
 * department's tasks. Interns reach only the tasks they are on.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Standalone tasks: anyone. Project tasks: see ProjectPolicy::createTask. */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->hasFullAccess() || $task->involves($user)) {
            return true;
        }

        if ($user->isIntern() || ! $user->department_id || $task->department_id !== $user->department_id) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        $project = $task->project;

        return $project !== null
            && ($project->owner_id === $user->id || $project->created_by === $user->id || $project->isMember($user));
    }

    /** Quick update: status, progress, remarks, proof of work, "working on it". */
    public function update(User $user, Task $task): bool
    {
        return $task->involves($user) || $this->manages($user, $task);
    }

    /** Change details, dates and the assignee. */
    public function edit(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            || ($task->assignee_id === $user->id && ! $user->isIntern())
            || $this->manages($user, $task);
    }

    public function manageCollaborators(User $user, Task $task): bool
    {
        return $this->edit($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->created_by === $user->id || $this->manages($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function addSubtask(User $user, Task $task): bool
    {
        if (! $task->canHaveChildren() || $user->isIntern()) {
            return false;
        }

        return $task->project
            ? $user->can('createTask', $task->project)
            : $this->edit($user, $task);
    }

    /** Mark other people's remarks on this task as reviewed. */
    public function reviewRemarks(User $user, Task $task): bool
    {
        return $user->hasFullAccess() || $this->manages($user, $task);
    }

    /**
     * Supervisors of the task: its department's supervisor, or a project manager of its project
     * within the same department.
     */
    private function manages(User $user, Task $task): bool
    {
        if ($user->managesDepartment($task->department_id)) {
            return true;
        }

        return $task->project !== null
            && $user->department_id !== null
            && $task->department_id === $user->department_id
            && $task->project->isProjectManager($user);
    }
}
