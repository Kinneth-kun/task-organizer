<?php

namespace App\Livewire;

use App\Enums\ProjectHealth;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\DailyReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Full-screen view for management meetings: overview, the latest updates since the previous
 * working day (the daily report), what needs attention, what's coming up and recent wins.
 * Arrow keys move between sections.
 */
#[Layout('layouts::bare')]
#[Title('Meeting mode')]
class Meeting extends Component
{
    public function render(): View
    {
        $this->authorize('admin');

        $projects = Project::query()->open()->with(['owner', 'department'])
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()])
            ->get()
            ->sortBy(fn (Project $p) => [$p->health->urgency(), $p->due_date?->timestamp ?? PHP_INT_MAX])
            ->values();

        $since = DailyReport::meetingSince();
        $updates = DailyReport::query(auth()->user(), [$since, today()])->limit(40)->get();

        return view('livewire.meeting', [
            'projects' => $projects,
            'updatesSince' => $since,
            'updates' => DailyReport::byProject($updates),
            'updateChanges' => DailyReport::changes($updates),
            'updatesPending' => $updates->whereNull('reviewed_at')->count(),
            'attention' => $projects->filter(fn (Project $p) => in_array($p->health, [ProjectHealth::Delayed, ProjectHealth::AtRisk], true))->values(),
            'overdueTasks' => Task::query()->overdue()->with(['project', 'assignee'])
                ->orderBy('due_date')->limit(12)->get(),
            'upcoming' => Task::query()->open()->with(['project', 'assignee'])
                ->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays(7))
                ->orderBy('due_date')->limit(15)->get(),
            'deadlines' => Project::query()->open()->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays(14))->orderBy('due_date')->get(),
            'wins' => Task::query()->where('status', TaskStatus::Completed->value)->where('completed_at', '>=', now()->subDays(7))
                ->with(['project', 'assignee'])->latest('completed_at')->limit(12)->get(),
        ]);
    }
}
