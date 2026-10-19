<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\Toasts;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectHealthService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\TaskTree;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProjectShow extends Component
{
    use Toasts;

    public Project $project;

    /** tasks | board | team | overview */
    #[Url]
    public string $tab = 'tasks';

    /* Quick add */
    public string $newTitle = '';

    public string $newAssignee = '';

    public string $newDue = '';

    public string $newCategory = 'enhancement';

    /* Team */
    public string $memberId = '';

    public string $memberRole = 'member';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->newAssignee = auth()->user()->isAssignable() ? (string) auth()->id() : '';
    }

    private function fresh(): void
    {
        $this->project->refresh();
        $this->project->unsetRelation('members');
        unset($this->tasks, $this->evaluation);
    }

    /* ================================================================== Tasks */

    public function addTask(TaskService $tasks): void
    {
        $this->authorize('createTask', $this->project);
        $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newAssignee' => ['nullable', 'integer', 'exists:users,id'],
            'newDue' => ['nullable', 'date'],
            'newCategory' => ['nullable', 'in:enhancement,bug_fix,update'],
        ], [], ['newTitle' => 'title', 'newAssignee' => 'assignee', 'newDue' => 'due date']);

        $tasks->create([
            'project_id' => $this->project->id,
            'title' => $this->newTitle,
            'assignee_id' => $this->newAssignee ?: null,
            'due_date' => $this->newDue ?: null,
            'category' => $this->project->isCompleted() ? $this->newCategory : null,
        ], auth()->user());

        $this->reset(['newTitle', 'newDue']);
        $this->fresh();
        $this->toast('Task added.');
    }

    /** Kanban drop: wire:sort passes the item key, its new position and the column's group id. */
    public function moveTask(int|string $taskId, int $position, string $status, TaskService $tasks): void
    {
        $task = Task::where('project_id', $this->project->id)->findOrFail($taskId);
        $this->authorize('update', $task);

        if ($task->hasChildren()) {
            $this->toast('This task follows its subtasks — update those instead.', 'error');

            return;
        }

        $target = TaskStatus::tryFrom($status);
        if ($target && $target !== $task->status) {
            $tasks->applyUpdate($task, auth()->user(), $target->value, null, null);
            $task->refresh();
            $this->toast($task->status === $target ? "Moved to {$target->label()}." : "Kept as {$task->status->label()} — the task is past its due date.", $task->status === $target ? 'success' : 'info');
        }

        $this->fresh();
    }

    /* ================================================================== Project status */

    public function complete(ProjectService $projects): void
    {
        $this->authorize('update', $this->project);
        $projects->complete($this->project, auth()->user());
        $this->fresh();
        $this->toast('Project marked as completed. New tasks are now post-launch work.');
    }

    public function reopen(ProjectService $projects): void
    {
        $this->authorize('update', $this->project);
        $projects->reopen($this->project, auth()->user());
        $this->fresh();
        $this->toast('Project reopened.');
    }

    public function setStatus(string $status, ProjectService $projects): void
    {
        $this->authorize('update', $this->project);
        $projects->setStatus($this->project, ProjectStatus::from($status), auth()->user());
        $this->fresh();
        $this->toast('Project status updated.');
    }

    public function deleteProject(ProjectService $projects): void
    {
        $this->authorize('delete', $this->project);
        $projects->delete($this->project, auth()->user());
        $this->flashToast('Project deleted.', 'info');
        $this->redirectRoute('projects.index');
    }

    /* ================================================================== Team */

    public function addMember(ProjectService $projects): void
    {
        $this->authorize('manageMembers', $this->project);
        $this->validate([
            'memberId' => ['required', 'integer', 'exists:users,id'],
            'memberRole' => ['required', Rule::enum(ProjectMemberRole::class)],
        ], [], ['memberId' => 'person']);

        $projects->addMember($this->project, (int) $this->memberId, $this->memberRole, auth()->user())
            ? $this->toast('Member added.')
            : $this->toast('That person is already on the team or can\'t be added.', 'error');

        $this->memberId = '';
        $this->fresh();
    }

    public function changeRole(int $userId, string $role, ProjectService $projects): void
    {
        $this->authorize('manageMembers', $this->project);
        $projects->changeMemberRole($this->project, $userId, $role, auth()->user());
        $this->fresh();
        $this->toast('Role updated.');
    }

    public function removeMember(int $userId, ProjectService $projects): void
    {
        $this->authorize('manageMembers', $this->project);
        $projects->removeMember($this->project, $userId, auth()->user());
        $this->fresh();
        $this->toast('Member removed.', 'info');
    }

    /* ================================================================== Data */

    /** Visible tasks, depth-first, numbered. */
    #[Computed]
    public function tasks(): Collection
    {
        $user = auth()->user();
        $numbers = TaskTree::numbersForProject($this->project->id);

        $tasks = Task::query()->visibleTo($user)
            ->where('project_id', $this->project->id)
            ->with(['assignee', 'parent', 'project'])
            ->withCount('children')
            ->get()
            ->each(fn (Task $t) => $t->setAttribute('number', $numbers[$t->id] ?? null));

        return TaskTree::flatten($tasks);
    }

    #[Computed]
    public function evaluation(): array
    {
        return app(ProjectHealthService::class)->evaluate($this->project);
    }

    #[Computed]
    public function people(): Collection
    {
        return User::query()->assignable()->with('department')->orderBy('name')->get();
    }

    public function render(): View
    {
        $this->project->loadMissing(['owner', 'creator', 'department', 'members.department']);
        $tasks = $this->tasks;

        return view('livewire.projects.project-show', [
            'buildTasks' => $tasks->filter(fn (Task $t) => $t->category === null)->values(),
            'postLaunch' => $tasks->filter(fn (Task $t) => $t->category !== null)->values(),
            'columns' => TaskStatus::cases(),
        ])->title($this->project->name);
    }
}
