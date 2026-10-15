<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The My Tasks filters, shared by the task list and the CSV export.
 *
 * view:       mine (assigned to me) | created (assigned by me) | involved | all (everything visible)
 * status:     a TaskStatus value, or "open"
 * priority:   a Priority value
 * project:    a project id, or "standalone"
 * department: a department id
 * due:        overdue | today | week | none
 * q:          text search in title and description
 */
class TaskFilters
{
    public const VIEWS = [
        'mine' => 'Assigned to me',
        'created' => 'Assigned by me',
        'involved' => 'Everything I\'m on',
        'all' => 'All visible',
    ];

    public const DUE = [
        'overdue' => 'Overdue',
        'today' => 'Due today',
        'week' => 'Due in 7 days',
        'none' => 'No due date',
    ];

    /** @param  array<string, mixed>  $filters */
    public static function apply(Builder $query, array $filters, User $user): Builder
    {
        $view = $filters['view'] ?? 'mine';

        match ($view) {
            'mine' => $query->where('tasks.assignee_id', $user->id),
            'created' => $query->where('tasks.created_by', $user->id)->where(fn ($q) => $q->whereNull('tasks.assignee_id')->orWhere('tasks.assignee_id', '!=', $user->id)),
            'involved' => $query->involving($user),
            default => null,
        };

        $status = $filters['status'] ?? null;
        if ($status === 'open') {
            $query->open();
        } elseif ($status && TaskStatus::tryFrom($status)) {
            $query->where('tasks.status', $status);
        }

        if (! empty($filters['priority'])) {
            $query->where('tasks.priority', $filters['priority']);
        }

        $project = $filters['project'] ?? null;
        if ($project === 'standalone') {
            $query->whereNull('tasks.project_id');
        } elseif ($project) {
            $query->where('tasks.project_id', (int) $project);
        }

        if (! empty($filters['department'])) {
            $query->where('tasks.department_id', (int) $filters['department']);
        }

        match ($filters['due'] ?? null) {
            'overdue' => $query->open()->whereDate('tasks.due_date', '<', today()),
            'today' => $query->whereDate('tasks.due_date', today()),
            'week' => $query->whereDate('tasks.due_date', '>=', today())->whereDate('tasks.due_date', '<=', today()->addDays(7)),
            'none' => $query->whereNull('tasks.due_date'),
            default => null,
        };

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where(fn ($q) => $q->where('tasks.title', 'like', "%{$term}%")->orWhere('tasks.description', 'like', "%{$term}%"));
        }

        return $query;
    }
}
