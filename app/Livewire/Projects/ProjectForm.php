<?php

namespace App\Livewire\Projects;

use App\Enums\Priority;
use App\Enums\ProjectMemberRole;
use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use App\Support\Palette;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Create a project (with its team and a first set of tasks and subtasks) or edit one.
 */
class ProjectForm extends Component
{
    use Toasts;

    public ?Project $project = null;

    public string $name = '';

    public string $description = '';

    public string $ownerId = '';

    public string $departmentId = '';

    public string $priority = 'medium';

    public string $color = 'indigo';

    public string $startDate = '';

    public string $dueDate = '';

    /** @var array<int, string> user id => role (create only) */
    public array $members = [];

    /** @var list<array{title: string, assignee_id: string, start_date: string, due_date: string, subtasks: string}> */
    public array $taskRows = [];

    public function mount(?Project $project = null): void
    {
        $user = auth()->user();

        if ($project?->exists) {
            $this->authorize('update', $project);
            $this->project = $project;
            $this->name = $project->name;
            $this->description = (string) $project->description;
            $this->ownerId = (string) $project->owner_id;
            $this->departmentId = (string) ($project->department_id ?? '');
            $this->priority = $project->priority->value;
            $this->color = $project->color;
            $this->startDate = $project->start_date?->toDateString() ?? '';
            $this->dueDate = $project->due_date?->toDateString() ?? '';

            return;
        }

        $this->authorize('create', Project::class);
        $this->project = null;
        $this->ownerId = (string) $user->id;
        $this->departmentId = (string) ($user->department_id ?? '');
        $this->startDate = today()->toDateString();
        $this->color = Palette::CHOICES[array_rand(Palette::CHOICES)];
        $this->taskRows = [$this->blankRow(), $this->blankRow()];
    }

    private function blankRow(): array
    {
        return ['title' => '', 'assignee_id' => '', 'start_date' => '', 'due_date' => '', 'subtasks' => ''];
    }

    public function addRow(): void
    {
        $this->taskRows[] = $this->blankRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->taskRows[$index]);
        $this->taskRows = array_values($this->taskRows);
    }

    public function toggleMember(int $id): void
    {
        if (isset($this->members[$id])) {
            unset($this->members[$id]);
        } else {
            $this->members[$id] = ProjectMemberRole::Member->value;
        }
    }

    /** Who may own a project: oversight roles and department supervisors pick anyone; others own their own. */
    #[Computed]
    public function canPickOwner(): bool
    {
        $user = auth()->user();

        return $user->hasFullAccess() || $user->isManager() || ($this->project && $this->project->owner_id === $user->id);
    }

    #[Computed]
    public function people(): Collection
    {
        return User::query()->active()->where('role', '!=', 'intern')->with('department')->orderBy('name')->get();
    }

    #[Computed]
    public function assignable(): Collection
    {
        return User::query()->assignable()->with('department')->orderBy('name')->get();
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->active()->orderBy('name')->get();
    }

    public function save(ProjectService $projects): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'ownerId' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'color' => ['required', Rule::in(Palette::CHOICES)],
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date', ...($this->startDate !== '' ? ['after_or_equal:startDate'] : [])],
            'members' => ['array'],
            'members.*' => [Rule::enum(ProjectMemberRole::class)],
            'taskRows' => ['array'],
            'taskRows.*.title' => ['nullable', 'string', 'max:255'],
            'taskRows.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            // A task with subtasks needs both dates: its subtasks take them.
            'taskRows.*.start_date' => ['nullable', 'date', 'required_with:taskRows.*.subtasks'],
            'taskRows.*.due_date' => ['nullable', 'date', 'required_with:taskRows.*.subtasks'],
            'taskRows.*.subtasks' => ['nullable', 'string', 'max:5000'],
            ...collect($this->taskRows)
                ->filter(fn ($row) => ($row['start_date'] ?? '') !== '')
                ->mapWithKeys(fn ($row, $i) => ["taskRows.{$i}.due_date" => ["after_or_equal:taskRows.{$i}.start_date"]])
                ->all(),
        ], [
            'taskRows.*.start_date.required_with' => 'Add a start date — its subtasks use it.',
            'taskRows.*.due_date.required_with' => 'Add an end date — its subtasks use it.',
        ], ['ownerId' => 'owner', 'dueDate' => 'due date', 'startDate' => 'start date', 'taskRows.*.title' => 'task title', 'taskRows.*.start_date' => 'start date', 'taskRows.*.due_date' => 'end date']);

        if (! $this->canPickOwner) {
            $data['ownerId'] = $this->project?->owner_id ?? $user->id;
        }

        $values = [
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'owner_id' => (int) $data['ownerId'],
            'department_id' => $data['departmentId'] ?: null,
            'priority' => $data['priority'],
            'color' => $data['color'],
            'start_date' => $data['startDate'] ?: null,
            'due_date' => $data['dueDate'] ?: null,
        ];

        if ($this->project) {
            $this->authorize('update', $this->project);
            $project = $projects->update($this->project, $values, $user);
            $this->flashToast('Project updated.');
            $this->redirectRoute('projects.show', $project);

            return;
        }

        $this->authorize('create', Project::class);
        $project = $projects->create([
            ...$values,
            'members' => collect($this->members)->map(fn ($role, $id) => ['user_id' => (int) $id, 'role' => $role])->values()->all(),
            'tasks' => collect($this->taskRows)
                ->filter(fn ($row) => trim($row['title']) !== '')
                ->map(fn ($row) => [
                    'title' => $row['title'],
                    'assignee_id' => $row['assignee_id'] ?: null,
                    'start_date' => $row['start_date'] ?: null,
                    'due_date' => $row['due_date'] ?: null,
                    'subtasks' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $row['subtasks'] ?? '')))),
                ])->values()->all(),
        ], $user);

        $this->flashToast('Project created.');
        $this->redirectRoute('projects.show', $project);
    }

    public function render(): View
    {
        return view('livewire.projects.project-form')->title($this->project ? 'Edit project' : 'New project');
    }
}
