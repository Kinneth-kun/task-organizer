<?php

namespace App\Services;

use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\Settings;

/**
 * Project progress and health. Thresholds are administrator settings ("Project health").
 *
 * Rules, evaluated in order:
 *  1. Project status Completed                          → Completed
 *  2. Project status On Hold or Cancelled               → On Hold
 *  3. Has tasks and every non-cancelled task completed  → Completed
 *  4. Project past its due date (if enabled)            → Delayed
 *  5. Delayed share of open tasks ≥ delayed threshold   → Delayed
 *  6. Delayed share of open tasks ≥ at-risk threshold   → At Risk
 *  7. Behind expected (time-based) progress by ≥ gap    → At Risk
 *  8. Inside the due-soon window with low progress      → At Risk
 *  9. Otherwise                                         → On Track
 *
 * Only top-level build tasks count; subtasks roll up into their parents and post-launch items
 * (enhancements, bug fixes, updates on a completed project) are excluded.
 */
class ProjectHealthService
{
    /** Recalculate and persist the cached progress and health. */
    public function refresh(Project|int $project): ?Project
    {
        $project = $project instanceof Project ? $project : Project::find($project);
        if (! $project) {
            return null;
        }

        $progress = $this->calculateProgress($project);
        $project->progress = $progress;
        $health = $this->evaluate($project)['health'];

        if ($project->isDirty('progress') || $project->health !== $health) {
            $project->forceFill(['progress' => $progress, 'health' => $health])->saveQuietly();
        }

        return $project;
    }

    /** Recalculate every live project (the scheduled check, and after settings change). */
    public function refreshAll(): int
    {
        $count = 0;
        Project::query()->orderBy('id')->each(function (Project $project) use (&$count) {
            $this->refresh($project);
            $count++;
        });

        return $count;
    }

    /** Average progress of the project's top-level build tasks. */
    public function calculateProgress(Project $project): int
    {
        $average = $this->buildTasks($project)->avg('progress');

        return (int) round((float) ($average ?? 0));
    }

    /**
     * @return array{health: ProjectHealth, reasons: list<string>, stats: array<string, int|float>}
     */
    public function evaluate(Project $project): array
    {
        $counts = $this->buildTasks($project)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $count = fn (TaskStatus $status) => $counts[$status->value] ?? 0;
        $total = $counts->sum();
        $open = $count(TaskStatus::Pending) + $count(TaskStatus::InProgress);

        // Open tasks past their due date.
        $overdue = $this->buildTasks($project)
            ->whereIn('status', TaskStatus::openValues())
            ->whereDate('due_date', '<', today())
            ->count();
        $overduePercent = $open > 0 ? round($overdue / $open * 100, 1) : 0.0;
        $progress = $project->progress ?? $this->calculateProgress($project);

        $expected = null;
        if ($project->start_date && $project->due_date && $project->due_date->gt($project->start_date)) {
            $span = $project->start_date->diffInDays($project->due_date);
            $elapsed = $project->start_date->diffInDays(today(), false);
            $expected = (int) max(0, min(100, round($elapsed / $span * 100)));
        }

        $stats = [
            'total' => $total,
            'open' => $open,
            'completed' => $count(TaskStatus::Completed),
            'overdue' => $overdue,
            'overdue_percent' => $overduePercent,
            'progress' => $progress,
            'expected_progress' => $expected ?? -1,
        ];

        $result = fn (ProjectHealth $health, array $reasons) => ['health' => $health, 'reasons' => $reasons, 'stats' => $stats];

        if ($project->status === ProjectStatus::Completed) {
            return $result(ProjectHealth::Completed, ['Project is marked completed.']);
        }

        if (in_array($project->status, [ProjectStatus::OnHold, ProjectStatus::Cancelled], true)) {
            return $result(ProjectHealth::OnHold, ["Project is {$project->status->label()}."]);
        }

        if ($total > 0 && $count(TaskStatus::Completed) === $total) {
            return $result(ProjectHealth::Completed, ['All tasks are completed.']);
        }

        if (Settings::bool('health.overdue_project_is_delayed') && $project->isOverdue()) {
            return $result(ProjectHealth::Delayed, ['Project is past its due date ('.$project->due_date->format('M j, Y').').']);
        }

        if ($overdue > 0 && $overduePercent >= Settings::int('health.delayed_delayed_percent')) {
            return $result(ProjectHealth::Delayed, ["{$overdue} of {$open} open tasks are overdue ({$overduePercent}%)."]);
        }

        $reasons = [];

        if ($overdue > 0 && $overduePercent >= Settings::int('health.at_risk_delayed_percent')) {
            $reasons[] = "{$overdue} of {$open} open tasks are overdue ({$overduePercent}%).";
        }

        if ($expected !== null && $expected - $progress >= Settings::int('health.progress_gap_percent')) {
            $reasons[] = "Progress is {$progress}% but {$expected}% of the schedule has elapsed.";
        }

        $dueSoonEnd = today()->addDays(Settings::int('deadline.due_soon_days'));
        if ($project->due_date
            && $project->due_date->gte(today())
            && $project->due_date->lte($dueSoonEnd)
            && $progress < Settings::int('health.due_soon_progress_percent')) {
            $reasons[] = 'Due '.$project->due_date->format('M j')." with only {$progress}% progress.";
        }

        if ($reasons) {
            return $result(ProjectHealth::AtRisk, $reasons);
        }

        return $result(ProjectHealth::OnTrack, ['No overdue tasks and progress is on schedule.']);
    }

    private function buildTasks(Project $project)
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->whereNull('category');
    }
}
