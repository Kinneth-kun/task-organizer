<?php

namespace App\Livewire;

use App\Enums\ProjectHealth;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\Toasts;
use App\Models\ActiveTaskSession;
use App\Models\Department;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use App\Services\CollaborationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use Toasts;

    /** Department filter for supervisors ('' = everything they can see). */
    #[Url(as: 'department')]
    public string $department = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user->isSupervisor()) {
            $this->department = '';
        }
    }

    /* ------------------------------------------------------------------ Actions */

    public function dismissFocus(int $taskId, CollaborationService $collaboration): void
    {
        $task = Task::findOrFail($taskId);
        $collaboration->dismissForToday($task, auth()->user());
        $this->toast('Hidden from today\'s focus. It comes back tomorrow.', 'info');
    }

    public function unpin(int $taskId, CollaborationService $collaboration): void
    {
        $task = Task::findOrFail($taskId);
        if ($collaboration->toggleFocus($task, auth()->user())) {
            $collaboration->toggleFocus($task, auth()->user());
        }
        $this->toast('Unpinned.', 'info');
    }

    public function reviewRemark(int $remarkId, CollaborationService $collaboration): void
    {
        $remark = Remark::with('task')->findOrFail($remarkId);
        $this->authorize('review', $remark);
        $collaboration->review($remark, auth()->user());
        $this->toast('Marked as reported.');
    }

    public function stopWorking(CollaborationService $collaboration): void
    {
        $collaboration->stopWorking(auth()->user());
        $this->toast('Stopped working.', 'info');
    }

    /* ------------------------------------------------------------------ Data */

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->active()->orderBy('name')->get();
    }

    /** The department the supervisor filter resolves to (managers see their own by default). */
    private function departmentId(): ?int
    {
        return $this->department !== '' ? (int) $this->department : null;
    }

    private function visibleTasks(): Builder
    {
        $query = Task::query()->visibleTo(auth()->user());
        if ($id = $this->departmentId()) {
            $query->where('tasks.department_id', $id);
        }

        return $query;
    }

    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();
        $mine = Task::query()->where('assignee_id', $user->id);

        $stats = [
            'open' => (clone $mine)->open()->count(),
            'overdue' => (clone $mine)->open()->whereDate('due_date', '<', today())->count(),
            'today' => (clone $mine)->open()->whereDate('due_date', today())->count(),
            'done_week' => (clone $mine)->where('status', TaskStatus::Completed->value)->where('completed_at', '>=', now()->startOfWeek())->count(),
        ];

        if ($user->isSupervisor()) {
            $stats['unreviewed'] = Remark::query()->unreviewed()
                ->whereIn('task_id', $this->visibleTasks()->select('tasks.id'))
                ->where('user_id', '!=', $user->id)
                ->count();
            $stats['working'] = $this->working()->count();
        }

        return $stats;
    }

    #[Computed]
    public function focus(): Collection
    {
        return app(CollaborationService::class)->focusFor(auth()->user());
    }

    /** Live "currently working on" sessions on tasks the viewer can see. */
    #[Computed]
    public function working(): Collection
    {
        return ActiveTaskSession::query()->live()
            ->with(['user', 'task.project'])
            ->whereIn('task_id', $this->visibleTasks()->select('tasks.id'))
            ->orderBy('started_at')
            ->get();
    }

    #[Computed]
    public function mySession(): ?ActiveTaskSession
    {
        return ActiveTaskSession::query()->live()->with('task.project')->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function remarks(): Collection
    {
        return Remark::query()
            ->with(['user', 'reviewer', 'task.project', 'attachments'])
            ->whereIn('task_id', $this->visibleTasks()->select('tasks.id'))
            ->latest('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function myWork(): Collection
    {
        return Task::query()->open()->with(['project', 'assignee', 'parent'])
            ->where('assignee_id', auth()->id())
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function attention(): Collection
    {
        $query = Project::query()->visibleTo(auth()->user())->open()
            ->whereIn('health', [ProjectHealth::Delayed->value, ProjectHealth::AtRisk->value])
            ->with('owner');

        if ($id = $this->departmentId()) {
            $query->where('department_id', $id);
        }

        return $query->get()->sortBy(fn (Project $p) => [$p->health->urgency(), $p->due_date?->timestamp ?? PHP_INT_MAX])->take(6)->values();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
