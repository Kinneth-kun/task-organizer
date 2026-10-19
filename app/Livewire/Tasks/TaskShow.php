<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\ActiveTaskSession;
use App\Models\Attachment;
use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\CollaborationService;
use App\Services\TaskService;
use App\Support\TaskTree;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskShow extends Component
{
    use Toasts, WithFileUploads;

    public Task $task;

    #[Url]
    public string $tab = 'remarks';

    /* Quick update */
    public string $qStatus = '';

    public int $qProgress = 0;

    public string $qRemark = '';

    public array $proof = [];

    /* Comments */
    public string $commentBody = '';

    public array $commentFiles = [];

    /* Task files */
    public array $files = [];

    /* Inline subtask */
    public bool $addingSubtask = false;

    public string $subTitle = '';

    public string $subAssignee = '';

    public string $subStart = '';

    public string $subDue = '';

    /* Collaborators */
    public string $newCollaborator = '';

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->task = $task;
        $this->resetQuickUpdate();

        if (request()->boolean('add_subtask') && auth()->user()->can('addSubtask', $task)) {
            $this->openSubtask();
        }
    }

    private function resetQuickUpdate(): void
    {
        $this->task->refresh();
        $this->qStatus = $this->task->status->value;
        $this->qProgress = $this->task->progress;
        $this->qRemark = '';
        $this->proof = [];
    }

    private function refreshTask(): void
    {
        $this->task->refresh();
        unset($this->subtasks, $this->remarks, $this->comments, $this->history, $this->workingNow, $this->ancestors);
    }

    /* ================================================================== Quick update */

    public function saveUpdate(TaskService $tasks): void
    {
        $this->authorize('update', $this->task);

        $this->validate([
            'qStatus' => ['required', 'string'],
            'qProgress' => ['integer', 'between:0,100'],
            'qRemark' => ['nullable', 'string', 'max:5000'],
            'proof' => ['array', 'max:'.config('organizer.attachments.max_per_upload')],
            'proof.*' => AttachmentService::rules(),
        ], [], ['qRemark' => 'remark', 'proof.*' => 'proof file']);

        $update = $tasks->applyUpdate($this->task, auth()->user(), $this->qStatus, $this->qProgress, $this->qRemark, $this->proof);

        $this->resetQuickUpdate();
        $this->refreshTask();

        $update
            ? $this->toast('Update saved.')
            : $this->toast('Nothing changed.', 'info');
    }

    /* ================================================================== Subtasks */

    public function openSubtask(): void
    {
        $this->addingSubtask = true;
        $this->subTitle = '';
        // Subtasks need both dates; start from this task's.
        $this->subStart = ($this->task->start_date ?? today())->toDateString();
        $this->subDue = $this->task->due_date?->toDateString() ?? '';
        $this->subAssignee = auth()->user()->isAssignable() ? (string) auth()->id() : '';
    }

    public function addSubtask(TaskService $tasks): void
    {
        $this->authorize('addSubtask', $this->task);
        $this->validate([
            'subTitle' => ['required', 'string', 'max:255'],
            'subAssignee' => ['nullable', 'integer', 'exists:users,id'],
            'subStart' => ['required', 'date'],
            'subDue' => ['required', 'date', 'after_or_equal:subStart'],
        ], [], ['subTitle' => 'title', 'subAssignee' => 'assignee', 'subStart' => 'start date', 'subDue' => 'end date']);

        $tasks->create([
            'parent_id' => $this->task->id,
            'title' => $this->subTitle,
            'assignee_id' => $this->subAssignee ?: null,
            'start_date' => $this->subStart,
            'due_date' => $this->subDue,
        ], auth()->user());

        // Keep the dates for the next subtask.
        $this->subTitle = '';
        $this->refreshTask();
        $this->resetQuickUpdate();
        $this->toast('Subtask added.');
    }

    /* ================================================================== Comments & files */

    public function addComment(CollaborationService $collaboration): void
    {
        $this->authorize('comment', $this->task);
        $this->validate([
            'commentBody' => ['required', 'string', 'max:5000'],
            'commentFiles' => ['array', 'max:'.config('organizer.attachments.max_per_upload')],
            'commentFiles.*' => AttachmentService::rules(),
        ], [], ['commentBody' => 'comment', 'commentFiles.*' => 'file']);

        $collaboration->comment($this->task, $this->commentBody, $this->commentFiles, auth()->user());
        $this->reset(['commentBody', 'commentFiles']);
        $this->tab = 'comments';
        unset($this->comments);
        $this->toast('Comment posted.');
    }

    public function deleteComment(int $id, CollaborationService $collaboration): void
    {
        $comment = TaskComment::where('task_id', $this->task->id)->findOrFail($id);
        $this->authorize('delete', $comment);
        $collaboration->deleteComment($comment, auth()->user());
        unset($this->comments);
        $this->toast('Comment deleted.', 'info');
    }

    public function uploadFiles(CollaborationService $collaboration): void
    {
        $this->authorize('update', $this->task);
        $this->validate([
            'files' => ['required', 'array', 'max:'.config('organizer.attachments.max_per_upload')],
            'files.*' => AttachmentService::rules(),
        ], [], ['files.*' => 'file']);

        $count = $collaboration->attachToTask($this->task, $this->files, auth()->user());
        $this->files = [];
        $this->toast($count === 1 ? 'File uploaded.' : "{$count} files uploaded.");
    }

    public function deleteAttachment(int $id, AttachmentService $attachments): void
    {
        $attachment = Attachment::findOrFail($id);
        $this->authorize('delete', $attachment);
        abort_unless($attachment->task()?->id === $this->task->id, 404);
        $attachments->delete($attachment, auth()->user());
        unset($this->remarks, $this->comments);
        $this->toast('File deleted.', 'info');
    }

    /* ================================================================== Remarks */

    public function reviewRemark(int $id, CollaborationService $collaboration): void
    {
        $remark = Remark::where('task_id', $this->task->id)->findOrFail($id);
        $this->authorize('review', $remark);
        $collaboration->review($remark, auth()->user());
        unset($this->remarks);
        $this->toast('Update marked as reported.');
    }

    /* ================================================================== Collaborators */

    public function addCollaborator(TaskService $tasks): void
    {
        $this->authorize('manageCollaborators', $this->task);
        $this->validate(['newCollaborator' => ['required', 'integer', 'exists:users,id']], [], ['newCollaborator' => 'person']);

        $tasks->addCollaborator($this->task, (int) $this->newCollaborator, auth()->user())
            ? $this->toast('Collaborator added.')
            : $this->toast('That person can\'t be added.', 'error');

        $this->newCollaborator = '';
        $this->task->unsetRelation('collaborators');
    }

    public function removeCollaborator(int $userId, TaskService $tasks): void
    {
        $this->authorize('manageCollaborators', $this->task);
        $tasks->removeCollaborator($this->task, $userId, auth()->user());
        $this->task->unsetRelation('collaborators');
        $this->toast('Collaborator removed.', 'info');
    }

    /* ================================================================== Focus & working */

    public function toggleFocus(CollaborationService $collaboration): void
    {
        $this->authorize('view', $this->task);
        $pinned = $collaboration->toggleFocus($this->task, auth()->user());
        $this->toast($pinned ? 'Pinned to your Focus of the Day.' : 'Unpinned.', 'info');
    }

    public function startWorking(CollaborationService $collaboration): void
    {
        $this->authorize('update', $this->task);
        $collaboration->startWorking($this->task, auth()->user());
        unset($this->workingNow);
        $this->toast('You\'re now working on this task.');
    }

    public function stopWorking(CollaborationService $collaboration): void
    {
        $collaboration->stopWorking(auth()->user());
        unset($this->workingNow);
        $this->toast('Stopped working.', 'info');
    }

    /* ================================================================== Delete */

    public function deleteTask(TaskService $tasks): void
    {
        $this->authorize('delete', $this->task);
        $parent = $this->task->parent;
        $project = $this->task->project;
        $tasks->delete($this->task, auth()->user());

        $this->flashToast('Task deleted.', 'info');
        $this->redirect($parent ? route('tasks.show', $parent) : ($project ? route('projects.show', $project) : route('tasks.index')));
    }

    /* ================================================================== Data */

    #[Computed]
    public function number(): ?string
    {
        return TaskTree::numberFor($this->task);
    }

    /** Parent chain, root first. */
    #[Computed]
    public function ancestors(): Collection
    {
        $chain = collect();
        $current = $this->task->parent;
        $guard = 0;
        while ($current && $guard++ < 10) {
            $chain->prepend($current);
            $current = $current->parent;
        }

        return $chain;
    }

    /** Visible descendants, depth-first with numbers. */
    #[Computed]
    public function subtasks(): Collection
    {
        $ids = app(TaskService::class)->subtreeIds($this->task);
        array_shift($ids);
        if (! $ids) {
            return collect();
        }

        $user = auth()->user();
        $tasks = Task::query()->whereIn('id', $ids)->with(['assignee', 'project'])->get()
            ->filter(fn (Task $t) => $user->can('view', $t));

        $numbers = $this->task->project_id
            ? TaskTree::numbersForProject($this->task->project_id)
            : TaskTree::subtreeNumbers($this->ancestors->first() ?? $this->task);

        return TaskTree::flatten($tasks->each(fn (Task $t) => $t->setAttribute('number', $numbers[$t->id] ?? null)));
    }

    #[Computed]
    public function remarks(): Collection
    {
        return $this->task->remarks()->with(['user', 'reviewer', 'attachments'])->get();
    }

    #[Computed]
    public function comments(): Collection
    {
        return $this->task->comments()->with(['user', 'attachments'])->get();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->task->updates()->with('user')->limit(100)->get();
    }

    #[Computed]
    public function workingNow(): Collection
    {
        return ActiveTaskSession::query()->live()->with('user')->where('task_id', $this->task->id)->get();
    }

    #[Computed]
    public function people(): Collection
    {
        return User::query()->assignable()->with('department')->orderBy('name')->get();
    }

    public function render(): View
    {
        $user = auth()->user();
        $this->task->loadMissing(['project', 'assignee', 'creator', 'department', 'collaborators.department', 'latestUpdater', 'attachments.user']);

        return view('livewire.tasks.task-show', [
            'pinned' => $user->focusTasks()->where('tasks.id', $this->task->id)->exists(),
            'mySession' => ActiveTaskSession::query()->live()->where('user_id', $user->id)->first(),
        ])->title($this->task->title);
    }
}
