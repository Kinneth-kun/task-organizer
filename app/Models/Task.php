<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mutations go through App\Services\TaskService, which keeps status, progress, history,
 * roll-up and project health consistent.
 *
 * @property TaskStatus $status
 * @property Priority $priority
 * @property TaskCategory|null $category
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'parent_id', 'depth', 'title', 'description', 'category', 'status',
        'priority', 'progress', 'assignee_id', 'created_by', 'department_id', 'start_date', 'due_date',
        'completed_at', 'deadline_reminded_for', 'latest_remark', 'latest_update_at',
        'latest_update_by', 'position',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'category' => TaskCategory::class,
            'progress' => 'integer',
            'depth' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
            'deadline_reminded_for' => 'date',
            'completed_at' => 'datetime',
            'latest_update_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ Relations */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function latestUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'latest_update_by');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_collaborators')->withPivot('added_by')->withTimestamps();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class)->latest('id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(Remark::class)->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    public function focusedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_focus')->withTimestamps();
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveTaskSession::class);
    }

    /* ------------------------------------------------------------------ State */

    public function isStandalone(): bool
    {
        return $this->project_id === null;
    }

    public function isPostLaunch(): bool
    {
        return $this->category !== null;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->lt(today()) && $this->isOpen();
    }

    public function isCollaborator(User $user): bool
    {
        if ($this->relationLoaded('collaborators')) {
            return $this->collaborators->contains('id', $user->id);
        }

        return $this->collaborators()->where('users.id', $user->id)->exists();
    }

    /** Assignee, creator or collaborator. */
    public function involves(User $user): bool
    {
        return $this->assignee_id === $user->id || $this->created_by === $user->id || $this->isCollaborator($user);
    }

    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    public function canHaveChildren(): bool
    {
        return $this->depth < config('organizer.max_task_depth');
    }

    /**
     * "Due today", "Due in 3 days", "2 days overdue" — null for closed or undated tasks.
     */
    public function dueLabel(): ?string
    {
        if (! $this->due_date || ! $this->isOpen()) {
            return null;
        }

        $days = (int) today()->diffInDays($this->due_date, false);

        return match (true) {
            $days < -1 => abs($days).' days overdue',
            $days === -1 => '1 day overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => "Due in {$days} days",
        };
    }

    /* ------------------------------------------------------------------ Scopes */

    /**
     * Tasks a user may see.
     *
     *  - Administrators and executives: every task.
     *  - Everyone: tasks they are assigned to, created or collaborate on.
     *  - Members and managers: their own department's tasks in projects they own, created or belong to.
     *  - Department supervisors: every task owned by their department.
     *  - Interns: only the first rule.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasFullAccess()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('tasks.assignee_id', $user->id)
                ->orWhere('tasks.created_by', $user->id)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('task_collaborators')
                    ->whereColumn('task_collaborators.task_id', 'tasks.id')
                    ->where('task_collaborators.user_id', $user->id));

            if ($user->isIntern() || ! $user->department_id) {
                return;
            }

            if ($user->isManager()) {
                $q->orWhere('tasks.department_id', $user->department_id);

                return;
            }

            $q->orWhere(function (Builder $dept) use ($user) {
                $dept->where('tasks.department_id', $user->department_id)
                    ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('projects')
                        ->whereColumn('projects.id', 'tasks.project_id')
                        ->whereNull('projects.deleted_at')
                        ->where(fn ($p) => $p->where('projects.owner_id', $user->id)
                            ->orWhere('projects.created_by', $user->id)
                            ->orWhereExists(fn ($m) => $m->selectRaw('1')->from('project_members')
                                ->whereColumn('project_members.project_id', 'projects.id')
                                ->where('project_members.user_id', $user->id))));
            });
        });
    }

    /** Assigned to, created by or collaborated on by the user. */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('tasks.assignee_id', $user->id)
            ->orWhere('tasks.created_by', $user->id)
            ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('task_collaborators')
                ->whereColumn('task_collaborators.task_id', 'tasks.id')
                ->where('task_collaborators.user_id', $user->id)));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('tasks.status', TaskStatus::openValues());
    }

    /** Open tasks past their due date. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '<', today());
    }

    /** Build tasks (not post-launch items). */
    public function scopeBuild(Builder $query): Builder
    {
        return $query->whereNull('tasks.category');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('tasks.parent_id');
    }
}
