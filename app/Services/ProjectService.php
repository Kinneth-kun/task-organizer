<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Models\ActiveTaskSession;
use App\Models\Project;
use App\Models\ProjectSuggestion;
use App\Models\Task;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project changes. Membership is explicit: people join only when someone adds them, and
 * administrators and executives are never members (they already see everything).
 */
class ProjectService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ProjectHealthService $health,
        private readonly Notifier $notifier,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, owner_id?: int|null, department_id?: int|null,
     *     priority?: string|null, color?: string|null, start_date?: string|null, due_date?: string|null,
     *     members?: list<array{user_id: int, role?: string}>, tasks?: list<array{title: string, due_date?: string|null, assignee_id?: int|null, subtasks?: list<string>}>}  $data
     */
    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $owner = ! empty($data['owner_id']) ? User::findOrFail($data['owner_id']) : $actor;

            $project = Project::create([
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'owner_id' => $owner->id,
                'created_by' => $actor->id,
                'department_id' => $data['department_id'] ?? $owner->department_id,
                'status' => ProjectStatus::Active,
                'priority' => Priority::tryFrom($data['priority'] ?? '') ?? Priority::Medium,
                'color' => $data['color'] ?? 'indigo',
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]);

            Audit::log('project.created', "Created project \"{$project->name}\"", $project, [], $actor);

            foreach ($data['members'] ?? [] as $member) {
                $this->addMember($project, (int) $member['user_id'], $member['role'] ?? ProjectMemberRole::Member->value, $actor);
            }

            foreach ($data['tasks'] ?? [] as $item) {
                if (trim($item['title'] ?? '') === '') {
                    continue;
                }
                $task = $this->tasks->create([
                    'project_id' => $project->id,
                    'title' => $item['title'],
                    'start_date' => $item['start_date'] ?? null,
                    'due_date' => $item['due_date'] ?? null,
                    'assignee_id' => $item['assignee_id'] ?? null,
                ], $actor);

                foreach ($item['subtasks'] ?? [] as $subtitle) {
                    if (trim((string) $subtitle) !== '') {
                        // Subtasks take their task's start and end dates.
                        $this->tasks->create([
                            'parent_id' => $task->id,
                            'title' => $subtitle,
                            'start_date' => $item['start_date'] ?? null,
                            'due_date' => $item['due_date'] ?? null,
                        ], $actor);
                    }
                }
            }

            return $this->health->refresh($project) ?? $project;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $project->fill(array_intersect_key($data, array_flip([
                'name', 'description', 'owner_id', 'department_id', 'priority', 'color', 'start_date', 'due_date',
            ])));

            $dirty = array_keys($project->getDirty());
            if (! $dirty) {
                return $project;
            }

            $project->save();

            // The owner is not also a member row.
            if (in_array('owner_id', $dirty, true)) {
                $project->members()->detach($project->owner_id);
            }

            Audit::log('project.updated', "Edited project \"{$project->name}\" (".implode(', ', str_replace('_', ' ', $dirty)).')', $project, ['fields' => $dirty], $actor);

            return $this->health->refresh($project) ?? $project;
        });
    }

    public function complete(Project $project, User $actor): Project
    {
        $project->forceFill(['status' => ProjectStatus::Completed, 'completed_at' => now()])->save();
        Audit::log('project.completed', "Marked \"{$project->name}\" as completed", $project, [], $actor);
        $this->notifier->projectCompleted($project, $actor);

        return $this->health->refresh($project) ?? $project;
    }

    public function reopen(Project $project, User $actor): Project
    {
        $project->forceFill(['status' => ProjectStatus::Active, 'completed_at' => null])->save();
        Audit::log('project.reopened', "Reopened \"{$project->name}\"", $project, [], $actor);

        return $this->health->refresh($project) ?? $project;
    }

    public function setStatus(Project $project, ProjectStatus $status, User $actor): Project
    {
        if ($status === ProjectStatus::Completed) {
            return $this->complete($project, $actor);
        }

        $project->forceFill(['status' => $status, 'completed_at' => null])->save();
        Audit::log('project.status_changed', "Set \"{$project->name}\" to {$status->label()}", $project, [], $actor);

        return $this->health->refresh($project) ?? $project;
    }

    /** Soft-delete the project with its tasks (history is kept). */
    public function delete(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor) {
            $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');
            Task::query()->whereIn('id', $taskIds)->update(['deleted_at' => now()]);
            ActiveTaskSession::query()->whereIn('task_id', $taskIds)->delete();
            $project->delete();
            Audit::log('project.deleted', "Deleted project \"{$project->name}\"", $project, ['tasks' => $taskIds->count()], $actor);
        });
    }

    /* ------------------------------------------------------------------ Team */

    public function addMember(Project $project, int $userId, string $role, User $actor): bool
    {
        $user = User::find($userId);
        $role = ProjectMemberRole::tryFrom($role) ?? ProjectMemberRole::Member;

        if (! $user || ! $user->isAssignable() || $user->id === $project->owner_id || $project->members()->where('users.id', $user->id)->exists()) {
            return false;
        }

        $project->members()->attach($user->id, ['role' => $role->value, 'added_by' => $actor->id]);
        $project->unsetRelation('members');

        Audit::log('project.member_added', "Added {$user->name} to \"{$project->name}\" as {$role->label()}", $project, ['user_id' => $user->id], $actor);
        $this->notifier->projectMemberAdded($project, $user, $actor);

        return true;
    }

    public function changeMemberRole(Project $project, int $userId, string $role, User $actor): void
    {
        $role = ProjectMemberRole::tryFrom($role) ?? throw ValidationException::withMessages(['role' => 'Choose a valid role.']);
        $project->members()->updateExistingPivot($userId, ['role' => $role->value]);
        $project->unsetRelation('members');

        $name = User::find($userId)?->name ?? 'a member';
        Audit::log('project.member_role_changed', "Made {$name} {$role->label()} on \"{$project->name}\"", $project, ['user_id' => $userId], $actor);
    }

    public function removeMember(Project $project, int $userId, User $actor): void
    {
        if ($project->members()->detach($userId)) {
            $project->unsetRelation('members');
            $name = User::find($userId)?->name ?? 'a member';
            Audit::log('project.member_removed', "Removed {$name} from \"{$project->name}\"", $project, ['user_id' => $userId], $actor);
        }
    }

    /* ------------------------------------------------------------------ Suggestions */

    /** @param  list<UploadedFile>  $files */
    public function addSuggestion(Project $project, string $body, array $files, User $actor): ProjectSuggestion
    {
        return DB::transaction(function () use ($project, $body, $files, $actor) {
            $suggestion = $project->suggestions()->create(['user_id' => $actor->id, 'body' => trim($body)]);

            foreach (array_slice($files, 0, config('organizer.attachments.max_per_upload')) as $file) {
                $this->attachments->store($file, $suggestion, $actor);
            }

            Audit::log('project.suggestion_added', "Left a suggestion on \"{$project->name}\"", $project, ['suggestion_id' => $suggestion->id], $actor);
            $this->notifier->suggestionAdded($project, $suggestion, $actor);

            return $suggestion;
        });
    }

    public function deleteSuggestion(ProjectSuggestion $suggestion, User $actor): void
    {
        foreach ($suggestion->attachments as $attachment) {
            $this->attachments->delete($attachment, $actor);
        }
        Audit::log('project.suggestion_deleted', "Removed a suggestion from \"{$suggestion->project?->name}\"", $suggestion->project, [], $actor);
        $suggestion->delete();
    }
}
