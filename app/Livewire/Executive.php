<?php

namespace App\Livewire;

use App\Enums\ProjectHealth;
use App\Enums\TaskStatus;
use App\Models\ActiveTaskSession;
use App\Models\Department;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Organisation-wide oversight for administrators and executives.
 */
#[Title('Executive dashboard')]
class Executive extends Component
{
    #[Url]
    public string $department = '';

    private function tasks(): Builder
    {
        return Task::query()->when($this->department, fn ($q) => $q->where('tasks.department_id', $this->department));
    }

    private function projects(): Builder
    {
        return Project::query()->when($this->department, fn ($q) => $q->where('department_id', $this->department));
    }

    #[Computed]
    public function stats(): array
    {
        $health = $this->projects()->open()->selectRaw('health, count(*) as c')->groupBy('health')->pluck('c', 'health');

        return [
            'projects' => (int) $health->sum(),
            'health' => collect(ProjectHealth::cases())->mapWithKeys(fn ($h) => [$h->value => (int) ($health[$h->value] ?? 0)]),
            'open' => $this->tasks()->open()->count(),
            'overdue' => $this->tasks()->open()->whereDate('due_date', '<', today())->count(),
            'completed30' => $this->tasks()->where('status', TaskStatus::Completed->value)->where('completed_at', '>=', now()->subDays(30))->count(),
            'working' => ActiveTaskSession::query()->live()->whereIn('task_id', $this->tasks()->select('tasks.id'))->count(),
            'unreviewed' => Remark::query()->unreviewed()->whereIn('task_id', $this->tasks()->select('tasks.id'))->count(),
        ];
    }

    /** @return Collection<string, int> status => count */
    #[Computed]
    public function distribution(): Collection
    {
        $counts = $this->tasks()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]);
    }

    #[Computed]
    public function byDepartment(): Collection
    {
        $grouped = fn (Builder $query) => $query->whereNotNull('department_id')->selectRaw('department_id, count(*) as c')->groupBy('department_id')->pluck('c', 'department_id');

        $open = $grouped(Task::query()->open());
        $overdue = $grouped(Task::query()->overdue());
        $progress = $grouped(Task::query()->where('status', TaskStatus::InProgress->value));
        $done = $grouped(Task::query()->where('status', TaskStatus::Completed->value)->where('completed_at', '>=', now()->subDays(30)));
        $unreviewed = Remark::query()->unreviewed()->join('tasks', 'tasks.id', '=', 'remarks.task_id')
            ->whereNotNull('tasks.department_id')->whereNull('tasks.deleted_at')
            ->selectRaw('tasks.department_id, count(*) as c')->groupBy('tasks.department_id')->pluck('c', 'department_id');

        return Department::query()->withCount(['users' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()
            ->map(fn (Department $d) => [
                'department' => $d,
                'people' => $d->users_count,
                'open' => (int) ($open[$d->id] ?? 0),
                'in_progress' => (int) ($progress[$d->id] ?? 0),
                'overdue' => (int) ($overdue[$d->id] ?? 0),
                'done30' => (int) ($done[$d->id] ?? 0),
                'unreviewed' => (int) ($unreviewed[$d->id] ?? 0),
            ]);
    }

    #[Computed]
    public function portfolio(): Collection
    {
        return $this->projects()->open()->with(['owner', 'department'])
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()])
            ->get()
            ->sortBy(fn (Project $p) => [$p->health->urgency(), $p->due_date?->timestamp ?? PHP_INT_MAX])
            ->values();
    }

    #[Computed]
    public function departmentTasks(): Collection
    {
        if (! $this->department) {
            return collect();
        }

        return $this->tasks()->open()->with(['project', 'assignee', 'parent'])
            ->orderByRaw("case when due_date < ? then 0 when status = 'in_progress' then 1 else 2 end", [today()->toDateString()])
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->limit(30)->get();
    }

    public function render(): View
    {
        $this->authorize('full-access');

        return view('livewire.executive', [
            'selected' => $this->department ? Department::find($this->department) : null,
        ]);
    }
}
