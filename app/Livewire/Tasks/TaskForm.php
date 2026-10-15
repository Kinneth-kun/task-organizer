<?php

namespace App\Livewire\Tasks;

use App\Enums\Priority;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\Toasts;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Create a task (in a project, as a subtask, or standalone) or edit one.
 */
class TaskForm extends Component
{
    use Toasts;

    public ?Task $task = null;

    public string $projectId = '';

    public ?int $parentId = null;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $priority = 'medium';

    public string $assigneeId = '';

    public string $startDate = '';

    public string $dueDate = '';

    public string $status = 'pending';

    public int $progress = 0;

    /** @var list<int> */
    public array $collaboratorIds = [];

    public function mount(?Task $task = null): void
    {
        $user = auth()->user();

        if ($task?->exists) {
            $this->authorize('edit', $task);
            $this->task = $task;
            $this->projectId = (string) ($task->project_id ?? '');
            $this->parentId = $task->parent_id;
            $this->title = $task->title;
            $this->description = (string) $task->description;
            $this->category = $task->category?->value ?? '';
            $this->priority = $task->priority->value;
            $this->assigneeId = (string) ($task->assignee_id ?? '');
            $this->startDate = $task->start_date?->toDateString() ?? '';
            $this->dueDate = $task->due_date?->toDateString() ?? '';
            $this->collaboratorIds = $task->collaborators()->pluck('users.id')->all();

            return;
        }

        $this->task = null;
        $this->assigneeId = $user->isAssignable() ? (string) $user->id : '';

        if ($parentId = request()->integer('parent')) {
            $parent = Task::findOrFail($parentId);
            $this->authorize('addSubtask', $parent);
            $this->parentId = $parent->id;
            $this->projectId = (string) ($parent->project_id ?? '');
            $this->dueDate = $parent->due_date?->toDateString() ?? '';
        } elseif ($projectId = request()->integer('project')) {
            $project = Project::findOrFail($projectId);
            $this->authorize('createTask', $project);
            $this->projectId = (string) $project->id;
        }
    }

    public function setDue(int $days): void
    {
        $this->dueDate = today()->addDays($days)->toDateString();
    }

    #[Computed]
    public function parent(): ?Task
    {
        return $this->parentId ? Task::with('project')->find($this->parentId) : null;
    }

    #[Computed]
    public function selectedProject(): ?Project
    {
        return $this->projectId !== '' ? Project::find($this->projectId) : null;
    }

    /** Projects this person can add tasks to. */
    #[Computed]
    public function projects(): Collection
    {
        $user = auth()->user();

        return Project::query()->visibleTo($user)->orderBy('name')->get()
            ->filter(fn (Project $p) => $user->can('createTask', $p))
            ->values();
    }

    #[Computed]
    public function people(): Collection
    {
        $user = auth()->user();
        if ($user->isIntern()) {
            return User::query()->whereKey($user->id)->with('department')->get();
        }

        return User::query()->assignable()->with('department')->orderBy('name')->get();
    }

    public function toggleCollaborator(int $id): void
    {
        $this->collaboratorIds = in_array($id, $this->collaboratorIds, true)
            ? array_values(array_diff($this->collaboratorIds, [$id]))
            : [...$this->collaboratorIds, $id];
    }

    public function save(TaskService $tasks): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'category' => ['nullable', Rule::enum(TaskCategory::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'assigneeId' => ['nullable', 'integer', 'exists:users,id'],
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date', ...($this->startDate !== '' ? ['after_or_equal:startDate'] : [])],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'progress' => ['integer', 'between:0,100'],
            'collaboratorIds' => ['array'],
            'collaboratorIds.*' => ['integer', 'exists:users,id'],
        ], [], ['dueDate' => 'due date', 'startDate' => 'start date', 'assigneeId' => 'assignee']);

        $values = [
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'category' => $data['category'] ?: null,
            'priority' => $data['priority'],
            'assignee_id' => $data['assigneeId'] ?: null,
            'start_date' => $data['startDate'] ?: null,
            'due_date' => $data['dueDate'] ?: null,
        ];

        if ($this->task) {
            $this->authorize('edit', $this->task);
            $task = $tasks->updateDetails($this->task, $values, $user);

            $current = $task->collaborators()->pluck('users.id')->all();
            foreach (array_diff($this->collaboratorIds, $current) as $id) {
                $tasks->addCollaborator($task, (int) $id, $user);
            }
            foreach (array_diff($current, $this->collaboratorIds) as $id) {
                $tasks->removeCollaborator($task, (int) $id, $user);
            }

            $this->flashToast('Task updated.');
            $this->redirectRoute('tasks.show', $task);

            return;
        }

        if ($this->parent) {
            $this->authorize('addSubtask', $this->parent);
        } elseif ($this->selectedProject) {
            $this->authorize('createTask', $this->selectedProject);
        } else {
            $this->authorize('create', Task::class);
        }

        $task = $tasks->create([
            ...$values,
            'project_id' => $this->parent ? null : ($this->selectedProject?->id),
            'parent_id' => $this->parent?->id,
            'status' => $data['status'],
            'progress' => $data['progress'],
            'collaborator_ids' => $this->collaboratorIds,
        ], $user);

        $this->flashToast($this->parent ? 'Subtask created.' : 'Task created.');
        $this->redirectRoute('tasks.show', $task);
    }

    public function render(): View
    {
        return view('livewire.tasks.task-form')->title($this->task ? 'Edit task' : 'New task');
    }
}
