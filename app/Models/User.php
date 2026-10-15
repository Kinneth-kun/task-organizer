<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property UserRole $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'role',
        'department_id',
        'job_title',
        'avatar_color',
        'is_active',
        'password',
        'email_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'email_notifications' => 'array',
            'password' => 'hashed',
        ];
    }

    /* ------------------------------------------------------------------ Relations */

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function collaborations(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_collaborators')->withTimestamps();
    }

    public function focusTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_focus')->withTimestamps();
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(ActiveTaskSession::class);
    }

    /* ------------------------------------------------------------------ Roles */

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isExecutive(): bool
    {
        return $this->role === UserRole::Executive;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isIntern(): bool
    {
        return $this->role === UserRole::Intern;
    }

    /** Administrators and executives see every project and task. */
    public function hasFullAccess(): bool
    {
        return $this->role->hasFullAccess();
    }

    /** A department manager acting on something owned by their own department. */
    public function managesDepartment(?int $departmentId): bool
    {
        return $this->isManager() && $departmentId !== null && $this->department_id === $departmentId;
    }

    /** Can be the primary assignee or a collaborator (admins and executives already see everything). */
    public function isAssignable(): bool
    {
        return $this->is_active && ! $this->hasFullAccess();
    }

    /** Supervisors review remarks and see the department dashboard filter. */
    public function isSupervisor(): bool
    {
        return $this->hasFullAccess() || $this->isManager();
    }

    /* ------------------------------------------------------------------ Sign-in state */

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Sign this person out everywhere: database sessions and "remember me" cookies.
     */
    public function revokeSessions(?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /* ------------------------------------------------------------------ Presentation */

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->name;
    }

    public function wantsEmailFor(NotificationType $type): bool
    {
        return in_array($type->value, $this->email_notifications ?? [], true);
    }

    /* ------------------------------------------------------------------ Scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** People who can be assigned work or added as collaborators/members. */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->active()->whereNotIn('role', [UserRole::Admin->value, UserRole::Executive->value]);
    }
}
