<?php

namespace App\Livewire;

use App\Livewire\Concerns\Toasts;
use App\Models\Project;
use App\Services\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The "+ New task" button in the top bar: a fast way to capture work, assigned to yourself.
 */
class QuickCreate extends Component
{
    use Toasts;

    public bool $open = false;

    public string $title = '';

    public string $projectId = '';

    public string $due = '';

    public string $priority = 'medium';

    public function openModal(): void
    {
        $this->reset(['title', 'projectId', 'due', 'priority']);
        $this->resetValidation();
        $this->open = true;
    }

    public function setDue(int $days): void
    {
        $this->due = today()->addDays($days)->toDateString();
    }

    /** Projects the user can add tasks to. */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()->visibleTo(auth()->user())->open()->orderBy('name')->get()
            ->filter(fn (Project $p) => auth()->user()->can('createTask', $p))
            ->values();
    }

    public function save(TaskService $tasks): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'projectId' => ['nullable', 'integer'],
            'due' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
        ]);

        $user = auth()->user();
        $project = $this->projectId ? Project::find($this->projectId) : null;
        if ($project) {
            $this->authorize('createTask', $project);
        }

        $task = $tasks->create([
            'project_id' => $project?->id,
            'title' => $this->title,
            'due_date' => $this->due ?: null,
            'priority' => $this->priority,
        ], $user);

        $this->open = false;
        $this->flashToast('Task created.');
        $this->redirectRoute('tasks.show', $task);
    }

    public function render(): View
    {
        return view('livewire.quick-create');
    }
}
