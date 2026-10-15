<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Enums\TaskUpdateType;
use App\Models\ActiveTaskSession;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for task changes. It enforces the business rules:
 *
 *  - a task belongs to a project or stands alone; subtasks nest up to three levels and stay in
 *    their parent's project
 *  - tasks added to a completed project are post-launch items (enhancement / bug fix / update)
 *    and don't change the finished project's progress
 *  - one primary assignee (never an administrator or executive) plus collaborators; the task's
 *    department follows its assignee, else its creator
 *  - status ⇄ progress: 0% Pending, 1–99% In Progress, 100% Completed
 *  - a parent's progress is the average of its non-cancelled children and its status follows
 *    them; changes roll up level by level to the project
 *  - every status, progress, remark, assignment and detail change writes append-only history
 *  - overdue open tasks become Delayed; rescheduling clears an automatic delay
 */
class TaskService
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly ProjectHealthService $health,
        private readonly AttachmentService $attachments,
    ) {}

    /* ================================================================== Create */

    /**
     * @param  array{project_id?: int|null, parent_id?: int|null, title: string, description?: string|null,
     *     category?: string|null, priority?: string|null, assignee_id?: int|null, start_date?: string|null,
     *     due_date?: string|null, status?: string|null, progress?: int|null, collaborator_ids?: list<int>}  $data
     */
    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
            $parent = null;
            if (! empty($data['parent_id'])) {
                $parent = Task::find($data['parent_id']);
                if (! $parent || ! $parent->canHaveChildren()) {
                    throw ValidationException::withMessages(['parent_id' => 'Subtasks can be nested at most '.(config('organizer.max_task_depth') + 1).' levels deep.']);
                }
            }

            // A subtask always lives in its parent's project.
            $projectId = $parent ? $parent->project_id : ($data['project_id'] ?? null);
            $project = null;
            if ($projectId) {
                $project = Project::find($projectId);
                if (! $project) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is invalid.']);
                }
            }

            // Work added after a project is completed is post-launch; a subtask matches its parent.
            $category = $parent
                ? $parent->category
                : ($project?->status === ProjectStatus::Completed ? TaskCategory::tryFrom($data['category'] ?? '') ?? TaskCategory::Enhancement : null);

            $assignee = $this->resolveAssignee($data, $actor);

            $progress = self::clampProgress($data['progress'] ?? 0);
            $requested = TaskStatus::tryFrom($data['status'] ?? '') ?? TaskStatus::fromProgress($progress);
            [$status, $progress] = self::normalize($requested, $progress);

            $siblings = Task::query()->withTrashed();
            $siblings = $parent
                ? $siblings->where('parent_id', $parent->id)
                : ($project ? $siblings->where('project_id', $project->id)->whereNull('parent_id') : $siblings->whereNull('project_id')->whereNull('parent_id')->where('created_by', $actor->id));

            $task = Task::create([
                'project_id' => $project?->id,
                'parent_id' => $parent?->id,
                'depth' => $parent ? $parent->depth + 1 : 0,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'category' => $category,
                'priority' => Priority::tryFrom($data['priority'] ?? '') ?? Priority::Medium,
                'assignee_id' => $assignee?->id,
                'created_by' => $actor->id,
                'department_id' => ($assignee ?? $actor)->department_id,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => $status,
                'progress' => $progress,
                'completed_at' => $status === TaskStatus::Completed ? now() : null,
                'latest_update_at' => now(),
                'latest_update_by' => $actor->id,
                'position' => ((int) $siblings->max('position')) + 1,
            ]);

            $this->record($task, $actor, TaskUpdateType::Created, null, $status, null, $progress);

            $kind = $parent ? 'subtask' : ($category ? strtolower($category->label()) : 'task');
            Audit::log('task.created', "Created {$kind} \"{$task->title}\""
                .($parent ? " under \"{$parent->title}\"" : '')
                .($project ? " in {$project->name}" : ' (standalone)'), $task, ['project_id' => $project?->id], $actor);

            if ($assignee && $assignee->id !== $actor->id) {
                $this->notifier->taskAssigned($task, $assignee, $actor);
            }

            foreach ($data['collaborator_ids'] ?? [] as $userId) {
                $this->addCollaborator($task, (int) $userId, $actor);
            }

            $this->syncDelay($task->refresh());
            $this->afterChange($task, $actor);

            return $task->refresh();
        });
    }

    /* ================================================================== Quick update */

    /**
     * Apply a status / progress / remark update, optionally with proof-of-work files, and record
     * it in history. Returns null when nothing changed and no remark was given.
     *
     * @param  list<UploadedFile>  $proof
     */
    public function applyUpdate(Task $task, User $actor, ?string $status = null, int|string|null $progress = null, ?string $remark = null, array $proof = []): ?TaskUpdate
    {
        return DB::transaction(function () use ($task, $actor, $status, $progress, $remark, $proof) {
            $task->refresh();
            $remark = $remark !== null && trim($remark) !== '' ? trim($remark) : null;
            if ($remark === null && $proof) {
                $remark = 'Submitted proof of work.';
            }

            $oldStatus = $task->status;
            $oldProgress = $task->progress;

            // A parent's status and progress follow its subtasks; only remarks apply to it directly.
            $followsChildren = $task->hasChildren();
            $requestedStatus = $followsChildren ? null : TaskStatus::tryFrom((string) $status);
            $requestedProgress = ! $followsChildren && $progress !== null && $progress !== '' ? self::clampProgress($progress) : null;

            // Forms submit both fields; an unchanged status means "progress drives".
            if ($requestedStatus === $oldStatus && $requestedProgress !== null && $requestedProgress !== $oldProgress) {
                $requestedStatus = null;
            }

            [$newStatus, $newProgress] = self::resolve($oldStatus, $oldProgress, $requestedStatus, $requestedProgress);

            // Choosing Pending / In Progress on an overdue task keeps it Delayed.
            $beforeDelay = null;
            if ($newStatus->canAutoDelay() && $this->shouldBeDelayed($task)) {
                $beforeDelay = $newStatus;
                $newStatus = TaskStatus::Delayed;
            }

            $statusChanged = $newStatus !== $oldStatus;
            $progressChanged = $newProgress !== $oldProgress;

            if (! $statusChanged && ! $progressChanged && $remark === null) {
                return null;
            }

            $task->forceFill([
                'status' => $newStatus,
                'progress' => $newProgress,
                ...$this->statusSideFields($task, $newStatus, $oldStatus, $beforeDelay),
                ...($remark !== null ? ['latest_remark' => $remark] : []),
                'latest_update_at' => now(),
                'latest_update_by' => $actor->id,
            ])->save();

            $remarkModel = null;
            if ($remark !== null) {
                $remarkModel = Remark::create(['task_id' => $task->id, 'user_id' => $actor->id, 'body' => $remark]);
                foreach ($proof as $file) {
                    $this->attachments->store($file, $remarkModel, $actor);
                }
            }

            $update = $this->record(
                $task, $actor,
                $statusChanged || $progressChanged ? TaskUpdateType::Update : TaskUpdateType::Remark,
                $oldStatus, $newStatus, $oldProgress, $newProgress, $remark,
                array_filter([
                    'requested_status' => $beforeDelay?->value,
                    'remark_id' => $remarkModel?->id,
                    'proof_count' => $proof ? count($proof) : null,
                ]),
            );

            [$action, $description] = match (true) {
                $statusChanged && $newStatus === TaskStatus::Completed => ['task.completed', "Completed \"{$task->title}\""],
                $statusChanged => ['task.status_changed', "Changed \"{$task->title}\" from {$oldStatus->label()} to {$newStatus->label()}"],
                $progressChanged => ['task.progress_updated', "Updated \"{$task->title}\" progress {$oldProgress}% → {$newProgress}%"],
                default => ['remark.added', "Added a remark on \"{$task->title}\""],
            };
            Audit::log($action, $description, $task, [
                'old_status' => $oldStatus->value, 'new_status' => $newStatus->value,
                'old_progress' => $oldProgress, 'new_progress' => $newProgress,
            ], $actor);

            // People @mentioned in the remark get the mention instead of the general update.
            $mentioned = $remark !== null ? $this->notifier->mentions($remark, $task, $actor, $remarkModel ? ['type' => 'remark', 'id' => $remarkModel->id] : null) : [];
            if ($statusChanged && $newStatus === TaskStatus::Completed) {
                $this->notifier->taskCompleted($task, $actor, $remark, $mentioned);
            } else {
                $this->notifier->taskUpdated($task, $actor, [
                    'status' => $statusChanged ? $newStatus->label() : null,
                    'progress' => $progressChanged ? $newProgress : null,
                    'remark' => $remark,
                ], $mentioned);
            }

            // Closing a task ends any "working on it" session for it.
            if (! $newStatus->isOpen()) {
                ActiveTaskSession::query()->where('task_id', $task->id)->delete();
            }

            $this->afterChange($task, $actor);

            return $update;
        });
    }

    /* ================================================================== Edit details */

    /**
     * Update title, description, type, priority, dates and the assignee.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDetails(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $task->refresh();
            $fields = [
                'title' => 'title',
                'description' => 'description',
                'category' => 'type',
                'priority' => 'priority',
                'start_date' => 'start date',
                'due_date' => 'due date',
                'assignee_id' => 'assignee',
            ];

            if (array_key_exists('assignee_id', $data)) {
                $assignee = $this->resolveAssignee($data, $actor, $task);
                $data['assignee_id'] = $assignee?->id;
            }

            $changes = [];
            foreach ($fields as $key => $label) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }
                $old = $this->plain($task->{$key});
                $new = $this->plain($data[$key] === '' ? null : $data[$key]);
                if ((string) $old !== (string) $new) {
                    $changes[$key] = ['old' => $old, 'new' => $new, 'label' => $label];
                    $task->{$key} = $new;
                }
            }

            if (! $changes) {
                return $task;
            }

            // Only a completed project's top-level work has a type; build tasks never get one and
            // subtasks follow their parent.
            if (isset($changes['category']) && ($task->parent_id || ! $task->project?->isCompleted())) {
                $task->category = $task->getOriginal('category');
                unset($changes['category']);
            }

            if (isset($changes['due_date'])) {
                $task->deadline_reminded_for = null;
            }

            if (isset($changes['assignee_id'])) {
                $owner = $task->assignee_id ? User::find($task->assignee_id) : User::find($task->created_by);
                $task->department_id = $owner?->department_id;
            }

            $task->forceFill(['latest_update_at' => now(), 'latest_update_by' => $actor->id])->save();

            $assigneeChange = $changes['assignee_id'] ?? null;
            $detailChanges = array_diff_key($changes, ['assignee_id' => true]);

            if ($detailChanges) {
                $this->record($task, $actor, TaskUpdateType::Details, meta: [
                    'changes' => array_map(fn ($c) => ['old' => $c['old'], 'new' => $c['new']], $detailChanges),
                ]);
                $labels = array_column($detailChanges, 'label');
                Audit::log('task.updated', "Edited \"{$task->title}\" (".implode(', ', $labels).')', $task, ['changes' => $detailChanges], $actor);
                $this->notifier->taskEdited($task, $actor, $labels);
            }

            if ($assigneeChange) {
                $old = $assigneeChange['old'] ? User::find($assigneeChange['old']) : null;
                $new = $assigneeChange['new'] ? User::find($assigneeChange['new']) : null;
                $this->record($task, $actor, TaskUpdateType::Assignment, meta: [
                    'old_assignee_id' => $old?->id, 'old_assignee' => $old?->name,
                    'new_assignee_id' => $new?->id, 'new_assignee' => $new?->name,
                ]);
                Audit::log('task.assigned', $new ? "Assigned \"{$task->title}\" to {$new->name}" : "Unassigned \"{$task->title}\"", $task, [
                    'old_assignee_id' => $old?->id, 'new_assignee_id' => $new?->id,
                ], $actor);

                if ($new) {
                    // A person is never both the assignee and a collaborator.
                    $task->collaborators()->detach($new->id);
                    if ($new->id !== $actor->id) {
                        $this->notifier->taskAssigned($task, $new, $actor);
                    }
                }
            }

            if (isset($changes['due_date'])) {
                $this->syncDelay($task->refresh());
            }

            $this->afterChange($task, $actor);

            return $task->refresh();
        });
    }

    /* ================================================================== Collaborators */

    public function addCollaborator(Task $task, int $userId, User $actor): bool
    {
        $user = User::find($userId);
        if (! $user || ! $user->isAssignable() || $task->assignee_id === $user->id || $task->isCollaborator($user)) {
            return false;
        }

        $task->collaborators()->attach($user->id, ['added_by' => $actor->id]);
        $task->unsetRelation('collaborators');

        Audit::log('task.collaborator_added', "Added {$user->name} as a collaborator on \"{$task->title}\"", $task, ['user_id' => $user->id], $actor);
        $this->notifier->collaboratorAdded($task, $user, $actor);

        return true;
    }

    public function removeCollaborator(Task $task, int $userId, User $actor): bool
    {
        $removed = $task->collaborators()->detach($userId) > 0;
        $task->unsetRelation('collaborators');

        if ($removed) {
            $name = User::find($userId)?->name ?? 'a user';
            Audit::log('task.collaborator_removed', "Removed {$name} from \"{$task->title}\"", $task, ['user_id' => $userId], $actor);
        }

        return $removed;
    }

    /* ================================================================== Delete */

    /** Soft-delete a task and everything nested under it. */
    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $ids = $this->subtreeIds($task);
            $deletedAt = now();

            Task::query()->whereIn('id', $ids)->update(['deleted_at' => $deletedAt, 'updated_at' => $deletedAt]);
            ActiveTaskSession::query()->whereIn('task_id', $ids)->delete();

            Audit::log('task.deleted', "Deleted \"{$task->title}\"".(count($ids) > 1 ? ' and '.(count($ids) - 1).' subtask(s)' : '')
                .($task->project ? " from {$task->project->name}" : ''), $task, ['project_id' => $task->project_id], $actor);

            if ($task->parent_id && ($parent = Task::find($task->parent_id))) {
                $this->rollUp($parent, $actor);
                $this->afterChange($parent, $actor);
            } elseif ($task->project_id) {
                $this->health->refresh($task->project_id);
            }
        });
    }

    /** @return list<int> the task and all of its live descendants */
    public function subtreeIds(Task $task): array
    {
        $ids = [$task->id];
        $frontier = [$task->id];
        while ($frontier) {
            $frontier = Task::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            array_push($ids, ...$frontier);
        }

        return $ids;
    }

    /* ================================================================== Roll-up */

    /**
     * Set a parent's progress to the average of its non-cancelled children and its status to match
     * (0% Pending, 1–99% In Progress, 100% Completed; Delayed when overdue). On Hold and Cancelled
     * parents keep their status. A parent with no children left is left as it is.
     */
    public function rollUp(Task $parent, ?User $actor = null): void
    {
        $parent->refresh();
        $children = Task::query()->where('parent_id', $parent->id)->where('status', '!=', TaskStatus::Cancelled->value);
        if (! $children->exists()) {
            return;
        }

        $oldStatus = $parent->status;
        $oldProgress = $parent->progress;
        $progress = self::clampProgress((int) round((float) $children->avg('progress')));

        $status = $oldStatus;
        if (! in_array($oldStatus, [TaskStatus::OnHold, TaskStatus::Cancelled], true)) {
            $status = TaskStatus::fromProgress($progress);
            if ($status->canAutoDelay() && $this->shouldBeDelayed($parent)) {
                $status = TaskStatus::Delayed;
            }
        }

        if ($status === $oldStatus && $progress === $oldProgress) {
            return;
        }

        $parent->forceFill(['status' => $status, 'progress' => $progress, ...$this->statusSideFields($parent, $status, $oldStatus, null)])->save();
        $this->record($parent, $actor, TaskUpdateType::Rollup, $oldStatus, $status, $oldProgress, $progress);

        if ($status === TaskStatus::Completed && $oldStatus !== TaskStatus::Completed) {
            Audit::log('task.completed', "\"{$parent->title}\" was completed (all subtasks done)", $parent, [], $actor ?? false);
        }
    }

    /** After any change: roll up through every ancestor, then refresh the project. */
    public function afterChange(Task $task, ?User $actor = null): void
    {
        $current = $task;
        $guard = 0;
        while ($current->parent_id && $guard++ < 10) {
            $parent = Task::find($current->parent_id);
            if (! $parent) {
                break;
            }
            $this->rollUp($parent, $actor);
            $current = $parent;
        }

        if ($task->project_id) {
            $this->health->refresh($task->project_id);
        }
    }

    /* ================================================================== Delays */

    public function shouldBeDelayed(Task $task): bool
    {
        return Settings::bool('deadline.auto_delay_enabled')
            && $task->due_date !== null
            && $task->due_date->lt(today());
    }

    /** Apply or clear the automatic Delayed status from the due date. Returns true on a change. */
    public function syncDelay(Task $task): bool
    {
        $status = $task->status;

        if ($status->canAutoDelay() && $this->shouldBeDelayed($task)) {
            DB::transaction(function () use ($task, $status) {
                $task->forceFill(['status_before_delay' => $status, 'status' => TaskStatus::Delayed, 'delayed_at' => now()])->save();
                $this->record($task, null, TaskUpdateType::AutoDelayed, $status, TaskStatus::Delayed, $task->progress, $task->progress, meta: [
                    'due_date' => $task->due_date?->toDateString(),
                ]);
                Audit::log('task.delayed', "\"{$task->title}\" was automatically marked Delayed (due {$task->due_date->format('M j, Y')})", $task, [], false);
                $this->notifier->taskDelayed($task);
            });

            return true;
        }

        // Rescheduled → back to the progress-driven status. A manually chosen Delayed (no
        // delayed_at from the automatic check) is never cleared here.
        if ($status === TaskStatus::Delayed && $task->delayed_at !== null && ! $this->shouldBeDelayed($task)) {
            $restored = TaskStatus::fromProgress(min($task->progress, 99));
            $task->forceFill(['status' => $restored, 'status_before_delay' => null, 'delayed_at' => null])->save();
            $this->record($task, null, TaskUpdateType::Undelayed, TaskStatus::Delayed, $restored, $task->progress, $task->progress, meta: [
                'due_date' => $task->due_date?->toDateString(),
            ]);

            return true;
        }

        return false;
    }

    /* ================================================================== Status rules */

    public static function clampProgress(int|string|float|null $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /**
     * Make status and progress agree.
     *
     * @return array{0: TaskStatus, 1: int}
     */
    public static function normalize(TaskStatus $status, int $progress): array
    {
        return match ($status) {
            TaskStatus::Pending => [$status, 0],
            TaskStatus::Completed => [$status, 100],
            TaskStatus::InProgress => [$status, max(1, min(99, $progress))],
            default => [$status, $progress],
        };
    }

    /**
     * The status and progress after a request. An explicit status wins and progress is adjusted
     * to match; progress alone moves the status (Delayed / On Hold stay unless it reaches 100%).
     *
     * @return array{0: TaskStatus, 1: int}
     */
    public static function resolve(TaskStatus $oldStatus, int $oldProgress, ?TaskStatus $status, ?int $progress): array
    {
        if ($status !== null) {
            return self::normalize($status, $progress ?? $oldProgress);
        }

        if ($progress === null) {
            return [$oldStatus, $oldProgress];
        }

        return match ($oldStatus) {
            TaskStatus::Pending, TaskStatus::InProgress, TaskStatus::Completed => [TaskStatus::fromProgress($progress), $progress],
            TaskStatus::Delayed, TaskStatus::OnHold => [$progress >= 100 ? TaskStatus::Completed : $oldStatus, $progress],
            default => [$oldStatus, $progress],
        };
    }

    /* ================================================================== Internals */

    /**
     * The assignee for a create or edit: must be active and not an administrator or executive.
     * Interns may only assign themselves. Defaults to the creator on create.
     */
    private function resolveAssignee(array $data, User $actor, ?Task $existing = null): ?User
    {
        $id = $data['assignee_id'] ?? null;

        if ($id === null || $id === '') {
            if ($existing) {
                return null;
            }

            return $actor->isAssignable() ? $actor : null;
        }

        $user = User::find($id);
        if (! $user || ! $user->isAssignable()) {
            throw ValidationException::withMessages(['assignee_id' => 'Choose an active person who can be assigned work.']);
        }

        if ($actor->isIntern() && $user->id !== $actor->id && $user->id !== $existing?->assignee_id) {
            throw ValidationException::withMessages(['assignee_id' => 'Interns can only assign tasks to themselves.']);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function statusSideFields(Task $task, TaskStatus $newStatus, TaskStatus $oldStatus, ?TaskStatus $beforeDelay): array
    {
        $fields = ['completed_at' => $newStatus === TaskStatus::Completed ? ($task->completed_at ?? now()) : null];

        if ($newStatus === TaskStatus::Delayed) {
            if ($oldStatus !== TaskStatus::Delayed) {
                $fields['delayed_at'] = now();
                $fields['status_before_delay'] = $beforeDelay ?? $oldStatus;
            }
        } else {
            $fields['delayed_at'] = null;
            $fields['status_before_delay'] = null;
        }

        return $fields;
    }

    /** @param  array<string, mixed>  $meta */
    private function record(Task $task, ?User $actor, TaskUpdateType $type, ?TaskStatus $oldStatus = null, ?TaskStatus $newStatus = null,
        ?int $oldProgress = null, ?int $newProgress = null, ?string $remark = null, array $meta = []): TaskUpdate
    {
        return TaskUpdate::create([
            'task_id' => $task->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_progress' => $oldProgress,
            'new_progress' => $newProgress,
            'remark' => $remark,
            'meta' => $meta ?: null,
        ]);
    }

    /** A comparable scalar for enums and dates. */
    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }
}
