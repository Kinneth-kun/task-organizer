<?php

namespace App\Services;

use App\Models\ActiveTaskSession;
use App\Models\FocusDismissal;
use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comments, remark reviews, task files, Focus of the Day and "currently working on".
 */
class CollaborationService
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AttachmentService $attachments,
    ) {}

    /* ------------------------------------------------------------------ Comments */

    /** @param  list<UploadedFile>  $files */
    public function comment(Task $task, string $body, array $files, User $actor): TaskComment
    {
        return DB::transaction(function () use ($task, $body, $files, $actor) {
            $comment = $task->comments()->create(['user_id' => $actor->id, 'body' => trim($body)]);

            foreach (array_slice($files, 0, config('organizer.attachments.max_per_upload')) as $file) {
                $this->attachments->store($file, $comment, $actor);
            }

            Audit::log('comment.added', "Commented on \"{$task->title}\"", $task, ['comment_id' => $comment->id], $actor);

            $mentioned = $this->notifier->mentions($body, $task, $actor, ['type' => 'comment', 'id' => $comment->id]);
            $this->notifier->commentAdded($task, $comment, $actor, $mentioned);

            return $comment;
        });
    }

    public function deleteComment(TaskComment $comment, User $actor): void
    {
        DB::transaction(function () use ($comment, $actor) {
            foreach ($comment->attachments as $attachment) {
                $this->attachments->delete($attachment, $actor);
            }
            Audit::log('comment.deleted', "Deleted a comment on \"{$comment->task?->title}\"", $comment->task, [], $actor);
            $comment->delete();
        });
    }

    /* ------------------------------------------------------------------ Task files */

    /** @param  list<UploadedFile>  $files */
    public function attachToTask(Task $task, array $files, User $actor): int
    {
        $count = 0;
        foreach (array_slice($files, 0, config('organizer.attachments.max_per_upload')) as $file) {
            $attachment = $this->attachments->store($file, $task, $actor);
            $this->notifier->attachmentAdded($task, $attachment, $actor);
            $count++;
        }

        return $count;
    }

    /* ------------------------------------------------------------------ Remark review */

    public function review(Remark $remark, User $reviewer): void
    {
        $remark->forceFill(['reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();
        Audit::log('remark.reviewed', "Reviewed {$remark->user?->name}'s remark on \"{$remark->task?->title}\"", $remark->task, ['remark_id' => $remark->id], $reviewer);
        $this->notifier->remarkReviewed($remark, $reviewer);
    }

    /* ------------------------------------------------------------------ Focus of the Day */

    /** Pin or unpin (☆). Returns true when the task is now pinned. */
    public function toggleFocus(Task $task, User $user): bool
    {
        $result = $user->focusTasks()->toggle($task->id);

        return in_array($task->id, $result['attached'], true);
    }

    /** Hide a task from today's focus list ("not today"). */
    public function dismissForToday(Task $task, User $user): void
    {
        FocusDismissal::query()->firstOrCreate(['user_id' => $user->id, 'task_id' => $task->id, 'date' => today()->toDateString()]);
    }

    public function restoreForToday(Task $task, User $user): void
    {
        FocusDismissal::query()->where(['user_id' => $user->id, 'task_id' => $task->id])->whereDate('date', today())->delete();
    }

    /**
     * Today's focus: pinned open tasks first, then open tasks assigned to the user that are
     * overdue or due today — minus anything dismissed for today.
     *
     * @return Collection<int, Task>
     */
    public function focusFor(User $user): Collection
    {
        $dismissed = FocusDismissal::query()->where('user_id', $user->id)->whereDate('date', today())->pluck('task_id');

        $with = ['project', 'assignee', 'parent'];

        $pinned = $user->focusTasks()->open()->with($with)->whereNotIn('tasks.id', $dismissed)
            ->orderBy('task_focus.created_at')->get()
            ->filter(fn (Task $task) => $user->can('view', $task))
            ->each(fn (Task $task) => $task->setAttribute('focus_reason', 'pinned'));

        $due = Task::query()->open()->with($with)
            ->where('assignee_id', $user->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->whereNotIn('id', $dismissed)
            ->whereNotIn('id', $pinned->pluck('id'))
            ->orderBy('due_date')->orderBy('id')
            ->get()
            ->each(fn (Task $task) => $task->setAttribute('focus_reason', $task->due_date->isToday() ? 'due_today' : 'overdue'));

        return $pinned->concat($due)->values();
    }

    /* ------------------------------------------------------------------ Currently working */

    public function startWorking(Task $task, User $user): ActiveTaskSession
    {
        $session = ActiveTaskSession::query()->updateOrCreate(['user_id' => $user->id], [
            'task_id' => $task->id,
            'started_at' => now(),
            'expires_at' => now()->addHours(config('organizer.working_session_hours')),
        ]);

        Audit::log('task.working_started', "Started working on \"{$task->title}\"", $task, [], $user);

        return $session;
    }

    public function stopWorking(User $user): void
    {
        $session = ActiveTaskSession::query()->where('user_id', $user->id)->first();
        if ($session) {
            Audit::log('task.working_stopped', "Stopped working on \"{$session->task?->title}\"", $session->task, [
                'minutes' => (int) $session->started_at->diffInMinutes(now()),
            ], $user);
            $session->delete();
        }
    }

    /** Remove sessions past their expiry. */
    public function expireSessions(): int
    {
        return ActiveTaskSession::query()->where('expires_at', '<=', now())->delete();
    }
}
