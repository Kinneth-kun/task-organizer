<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectSuggestion;
use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Who hears about what. The person who acted is never notified about their own action,
 * deactivated accounts are skipped, and task notifications only reach people who can open the task.
 */
class Notifier
{
    /* ------------------------------------------------------------------ Projects */

    public function projectMemberAdded(Project $project, User $member, ?User $actor): void
    {
        $this->send([$member->id], new AppNotification(
            NotificationType::ProjectMemberAdded,
            "You were added to \"{$project->name}\"",
            $actor ? "By {$actor->name}" : null,
            route('projects.show', $project, false),
            $actor?->id, $actor?->name,
            ['type' => 'project', 'id' => $project->id],
        ), $actor);
    }

    public function projectCompleted(Project $project, ?User $actor): void
    {
        $team = $project->members()->pluck('users.id')->push($project->owner_id);

        $this->send($team, new AppNotification(
            NotificationType::ProjectCompleted,
            "\"{$project->name}\" was marked completed",
            ($actor ? "By {$actor->name} · " : '').'Log enhancements, bug fixes and updates on the project page.',
            route('projects.show', $project, false),
            $actor?->id, $actor?->name,
            ['type' => 'project', 'id' => $project->id],
        ), $actor);
    }

    public function suggestionAdded(Project $project, ProjectSuggestion $suggestion, ?User $actor): void
    {
        $managers = $project->members()->wherePivot('role', 'manager')->pluck('users.id')->push($project->owner_id);

        $this->send($managers, new AppNotification(
            NotificationType::SuggestionAdded,
            ($actor?->name ?? 'Someone')." left a suggestion on \"{$project->name}\"",
            Str::limit($suggestion->body, 160),
            route('projects.index', ['q' => $project->name], false),
            $actor?->id, $actor?->name,
            ['type' => 'suggestion', 'id' => $suggestion->id],
        ), $actor);
    }

    /* ------------------------------------------------------------------ Task people */

    public function taskAssigned(Task $task, User $assignee, ?User $actor): void
    {
        $this->send([$assignee->id], $this->forTask(
            $task, NotificationType::TaskAssigned,
            ($actor?->name ?? 'Someone')." assigned you \"{$task->title}\"",
            $this->where($task).($task->due_date ? ' · Due '.$task->due_date->format('M j, Y') : ''),
            $actor,
        ), $actor);
    }

    public function collaboratorAdded(Task $task, User $user, ?User $actor): void
    {
        $this->send([$user->id], $this->forTask(
            $task, NotificationType::CollaboratorAdded,
            ($actor?->name ?? 'Someone')." added you as a collaborator on \"{$task->title}\"",
            $this->where($task),
            $actor,
        ), $actor);
    }

    /* ------------------------------------------------------------------ Task activity */

    /** @param  list<int>  $exclude  People already notified about the same change (e.g. mentioned). */
    public function taskCompleted(Task $task, ?User $actor, ?string $remark = null, array $exclude = []): void
    {
        $this->send($this->involved($task, $exclude), $this->forTask(
            $task, NotificationType::TaskCompleted,
            "\"{$task->title}\" was completed",
            $remark ? $this->who($actor).': “'.Str::limit($remark, 160).'”' : ($actor ? "Completed by {$actor->name} · " : '').$this->where($task),
            $actor,
        ), $actor);
    }

    /**
     * @param  array{status: string|null, progress: int|null, remark: string|null}  $change
     * @param  list<int>  $exclude
     */
    public function taskUpdated(Task $task, ?User $actor, array $change, array $exclude = []): void
    {
        $parts = array_filter([$change['status'], $change['progress'] !== null ? $change['progress'].'%' : null]);
        $summary = $parts ? implode(' · ', $parts) : null;

        $this->send($this->involved($task, $exclude), $this->forTask(
            $task, NotificationType::TaskUpdated,
            $summary
                ? $this->who($actor)." updated \"{$task->title}\" — {$summary}"
                : $this->who($actor)." added a remark on \"{$task->title}\"",
            $change['remark'] ? '“'.Str::limit($change['remark'], 160).'”' : $this->where($task),
            $actor,
        ), $actor);
    }

    /** @param  list<string>  $fields */
    public function taskEdited(Task $task, ?User $actor, array $fields): void
    {
        $this->send($this->involved($task), $this->forTask(
            $task, NotificationType::TaskUpdated,
            $this->who($actor)." edited \"{$task->title}\"",
            'Changed '.implode(', ', $fields).' · '.$this->where($task),
            $actor,
        ), $actor);
    }

    /** @param  list<int>  $exclude */
    public function commentAdded(Task $task, TaskComment $comment, ?User $actor, array $exclude = []): void
    {
        $this->send($this->involved($task, $exclude), $this->forTask(
            $task, NotificationType::CommentAdded,
            $this->who($actor)." commented on \"{$task->title}\"",
            Str::limit($comment->body, 160),
            $actor,
            ['type' => 'comment', 'id' => $comment->id],
        ), $actor);
    }

    public function attachmentAdded(Task $task, Attachment $attachment, ?User $actor): void
    {
        $this->send($this->involved($task), $this->forTask(
            $task, NotificationType::AttachmentAdded,
            $this->who($actor)." attached a file to \"{$task->title}\"",
            $attachment->original_name,
            $actor,
            ['type' => 'attachment', 'id' => $attachment->id],
        ), $actor);
    }

    public function remarkReviewed(Remark $remark, User $reviewer): void
    {
        $task = $remark->task;

        $this->send([$remark->user_id], $this->forTask(
            $task, NotificationType::RemarkReviewed,
            "{$reviewer->name} reviewed your remark on \"{$task->title}\"",
            '“'.Str::limit($remark->body, 160).'”',
            $reviewer,
            ['type' => 'remark', 'id' => $remark->id],
        ), $reviewer);
    }

    /* ------------------------------------------------------------------ System */

    public function taskDelayed(Task $task): void
    {
        $recipients = $this->stakeholders($task)->push($task->project?->owner_id)->filter();

        $this->send($this->canView($task, $recipients), $this->forTask(
            $task, NotificationType::TaskDelayed,
            "\"{$task->title}\" is now delayed",
            'It was due '.$task->due_date?->format('M j, Y').' · '.$this->where($task),
            null,
        ), null);
    }

    public function deadlineApproaching(Task $task, string $dueLabel): void
    {
        $recipients = $task->collaborators()->pluck('users.id')->push($task->assignee_id)->filter();

        $this->send($recipients, $this->forTask(
            $task, NotificationType::DeadlineApproaching,
            "\"{$task->title}\" — ".Str::lower($dueLabel),
            "Progress: {$task->progress}% · ".$this->where($task),
            null,
            extra: ['due_date' => $task->due_date?->toDateString()],
        ), null);
    }

    /**
     * @param  list<string>  $lines
     */
    public function dailyDigest(User $user, string $title, string $message, array $lines): void
    {
        $this->send([$user->id], new AppNotification(
            NotificationType::DailyDigest,
            $title,
            $message,
            route('tasks.index', ['view' => 'mine'], false),
            extra: ['lines' => $lines],
        ), null);
    }

    /* ------------------------------------------------------------------ Mentions */

    /**
     * Notify active users @mentioned in a comment or remark who can open the task.
     *
     * @return list<int> the ids notified
     */
    public function mentions(string $text, Task $task, ?User $actor, ?array $subject = null): array
    {
        $ids = $this->canView($task, self::mentionedUserIds($text))
            ->reject(fn ($id) => $id === $actor?->id)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $this->send($ids, $this->forTask(
            $task, NotificationType::Mentioned,
            $this->who($actor)." mentioned you on \"{$task->title}\"",
            Str::limit(strip_tags($text), 160),
            $actor,
            $subject,
        ), $actor);

        return $ids->all();
    }

    /** Active users mentioned by @username. */
    public static function mentionedUserIds(string $text): Collection
    {
        preg_match_all('/(?<![\w.])@([a-z0-9][a-z0-9._-]*[a-z0-9]|[a-z0-9])/i', $text, $matches);
        $usernames = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));

        if (! $usernames) {
            return collect();
        }

        return User::query()->active()->whereIn('username', $usernames)->pluck('id');
    }

    /* ------------------------------------------------------------------ Internals */

    /** Assignee, creator and collaborators. */
    public function stakeholders(Task $task): Collection
    {
        return $task->collaborators()->pluck('users.id')
            ->push($task->assignee_id, $task->created_by)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Everyone involved in a task — stakeholders and the project owner — who can open it.
     *
     * @param  list<int>  $exclude
     */
    private function involved(Task $task, array $exclude = []): Collection
    {
        $ids = $this->stakeholders($task)
            ->push($task->project?->owner_id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => in_array($id, $exclude, true));

        return $this->canView($task, $ids);
    }

    private function canView(Task $task, iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->unique();
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()->active()->whereIn('id', $ids)->get()
            ->filter(fn (User $user) => $user->can('view', $task))
            ->pluck('id')
            ->values();
    }

    private function forTask(Task $task, NotificationType $type, string $title, ?string $message, ?User $actor, ?array $subject = null, array $extra = []): AppNotification
    {
        return new AppNotification(
            $type, $title, $message,
            route('tasks.show', $task, false),
            $actor?->id, $actor?->name,
            $subject ?? ['type' => 'task', 'id' => $task->id],
            $extra,
        );
    }

    private function send(iterable $userIds, AppNotification $notification, ?User $actor): void
    {
        $ids = collect($userIds)->filter()->unique()->reject(fn ($id) => $id === $actor?->id);
        if ($ids->isEmpty()) {
            return;
        }

        $users = User::query()->active()->whereIn('id', $ids)->get();
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }

    private function who(?User $actor): string
    {
        return $actor?->name ?? 'Someone';
    }

    /** "Project: Website" or "Standalone task". */
    private function where(Task $task): string
    {
        return $task->project ? 'Project: '.$task->project->name : 'Standalone task';
    }
}
