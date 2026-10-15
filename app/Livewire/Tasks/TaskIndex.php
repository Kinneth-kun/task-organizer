<?php

namespace App\Livewire\Tasks;

use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My Tasks')]
class TaskIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'mine';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $project = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $due = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $sort = 'due';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'priority', 'project', 'department', 'due', 'q']);
        $this->resetPage();
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return [
            'view' => $this->view, 'status' => $this->status, 'priority' => $this->priority, 'project' => $this->project,
            'department' => $this->department, 'due' => $this->due, 'q' => $this->q,
        ];
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name', 'color']);
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return array<string, int> */
    #[Computed]
    public function viewCounts(): array
    {
        $user = auth()->user();
        $counts = [];
        foreach (array_keys(TaskFilters::VIEWS) as $view) {
            $counts[$view] = TaskFilters::apply(Task::query()->visibleTo($user), ['view' => $view, 'status' => 'open'], $user)->count();
        }

        return $counts;
    }

    public function render(): View
    {
        $user = auth()->user();
        $query = TaskFilters::apply(Task::query()->visibleTo($user), $this->filters(), $user)
            ->with(['project', 'assignee', 'parent']);

        match ($this->sort) {
            'priority' => $query->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")->orderBy('due_date'),
            'updated' => $query->orderByDesc('latest_update_at'),
            'created' => $query->orderByDesc('created_at'),
            default => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'),
        };

        return view('livewire.tasks.task-index', [
            'tasks' => $query->paginate(25),
            'hasFilters' => $this->status !== 'open' || $this->priority || $this->project || $this->department || $this->due || $this->q,
        ]);
    }
}
