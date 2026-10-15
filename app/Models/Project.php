<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProjectStatus $status
 * @property ProjectHealth $health
 * @property Priority $priority
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'owner_id', 'created_by', 'department_id', 'status', 'priority', 'color',
        'start_date', 'due_date', 'completed_at', 'progress', 'health',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'health' => ProjectHealth::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ Relations */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Top-level tasks only. */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(ProjectSuggestion::class)->latest();
    }

    /* ------------------------------------------------------------------ Membership */

    /** The member role of a user ('manager' | 'member'), or null when not a member. */
    public function memberRole(User $user): ?string
    {
        if ($this->relationLoaded('members')) {
            return $this->members->firstWhere('id', $user->id)?->pivot->role;
        }

        return $this->members()->where('users.id', $user->id)->first()?->pivot->role;
    }

    public function isMember(User $user): bool
    {
        return $this->owner_id === $user->id || $this->memberRole($user) !== null;
    }

    /** The owner and members with the project manager role. */
    public function isProjectManager(User $user): bool
    {
        return $this->owner_id === $user->id || $this->memberRole($user) === ProjectMemberRole::Manager->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === ProjectStatus::Completed;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && ! in_array($this->status, [ProjectStatus::Completed, ProjectStatus::Cancelled], true);
    }

    /* ------------------------------------------------------------------ Scopes */

    /**
     * Projects a user may see: full access sees all; a department manager sees their department's;
     * everyone else sees projects they own, created or belong to.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasFullAccess()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('projects.owner_id', $user->id)
                ->orWhere('projects.created_by', $user->id)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('project_members')
                    ->whereColumn('project_members.project_id', 'projects.id')
                    ->where('project_members.user_id', $user->id));

            if ($user->isManager() && $user->department_id) {
                $q->orWhere('projects.department_id', $user->department_id);
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value]);
    }
}
