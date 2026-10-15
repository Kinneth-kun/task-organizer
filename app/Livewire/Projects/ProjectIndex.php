<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectStatus;
use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectSuggestion;
use App\Services\AttachmentService;
use App\Services\ProjectService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Projects')]
class ProjectIndex extends Component
{
    use Toasts, WithFileUploads;

    /** open | active | on_hold | completed | cancelled | all */
    #[Url]
    public string $status = 'open';

    #[Url]
    public string $health = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $q = '';

    #[Url]
    public bool $mine = false;

    /* Suggestion modal */
    public ?int $suggestFor = null;

    public string $suggestion = '';

    public array $suggestionFiles = [];

    public function openSuggestion(int $projectId): void
    {
        $this->authorize('suggest', Project::findOrFail($projectId));
        $this->suggestFor = $projectId;
        $this->reset(['suggestion', 'suggestionFiles']);
        $this->resetValidation();
    }

    public function saveSuggestion(ProjectService $projects): void
    {
        $project = Project::findOrFail($this->suggestFor);
        $this->authorize('suggest', $project);
        $this->validate([
            'suggestion' => ['required', 'string', 'max:3000'],
            'suggestionFiles' => ['array', 'max:'.config('organizer.attachments.max_per_upload')],
            'suggestionFiles.*' => AttachmentService::rules(),
        ], [], ['suggestionFiles.*' => 'file']);

        $projects->addSuggestion($project, $this->suggestion, $this->suggestionFiles, auth()->user());
        $this->suggestFor = null;
        $this->toast('Suggestion shared with the project team.');
    }

    public function deleteSuggestion(int $id, ProjectService $projects): void
    {
        $suggestion = ProjectSuggestion::with('project')->findOrFail($id);
        $this->authorize('delete', $suggestion);
        $projects->deleteSuggestion($suggestion, auth()->user());
        $this->toast('Suggestion removed.', 'info');
    }

    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        $user = auth()->user();

        $projects = Project::query()->visibleTo($user)
            ->with(['owner', 'department', 'members', 'suggestions' => fn ($q) => $q->with(['user', 'attachments'])->limit(50)])
            ->withCount([
                'tasks as open_tasks_count' => fn ($q) => $q->open(),
                'tasks as total_tasks_count',
            ])
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when(ProjectStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status->value))
            ->when($this->health, fn ($q) => $q->where('health', $this->health))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->mine, fn ($q) => $q->where(fn ($w) => $w->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id))))
            ->when(trim($this->q) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.trim($this->q).'%')
                ->orWhere('description', 'like', '%'.trim($this->q).'%')))
            ->get()
            ->sortBy(fn (Project $p) => [$p->status === ProjectStatus::Completed ? 1 : 0, $p->health->urgency(), $p->due_date?->timestamp ?? PHP_INT_MAX])
            ->values();

        return view('livewire.projects.project-index', [
            'projects' => $projects,
            'suggestProject' => $this->suggestFor ? $projects->firstWhere('id', $this->suggestFor) ?? Project::find($this->suggestFor) : null,
        ]);
    }
}
