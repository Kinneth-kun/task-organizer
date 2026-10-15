<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * The weekday 8:00 am briefing: each person's overdue, due-today and due-soon work, plus what
 * they finished yesterday. Delivered as an in-app notification (and email for those who opted in).
 */
class DigestService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * @return array{open_total: int, overdue: Collection<int, Task>, due_today: Collection<int, Task>, due_soon: Collection<int, Task>, completed_yesterday: int}
     */
    public function workloadFor(User $user): array
    {
        $open = Task::query()->open()->with('project')
            ->where('assignee_id', $user->id)
            ->orderBy('due_date')
            ->get();

        $windowEnd = today()->addDays(Settings::int('deadline.due_soon_days'));

        return [
            'open_total' => $open->count(),
            'overdue' => $open->filter(fn (Task $t) => $t->due_date?->lt(today()))->values(),
            'due_today' => $open->filter(fn (Task $t) => $t->due_date?->isToday())->values(),
            'due_soon' => $open->filter(fn (Task $t) => $t->due_date?->gt(today()) && $t->due_date->lte($windowEnd))->values(),
            'completed_yesterday' => Task::query()
                ->where('assignee_id', $user->id)
                ->where('status', TaskStatus::Completed->value)
                ->whereBetween('completed_at', [today()->subDay(), today()])
                ->count(),
        ];
    }

    /**
     * @param  array{dry_run?: bool, force?: bool, user?: string|null}  $options
     * @return list<array{user: string, status: string, title?: string, message?: string, lines?: list<string>}>
     */
    public function send(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);

        if (! $force && ! Settings::bool('digest.enabled')) {
            return [['user' => '*', 'status' => 'disabled']];
        }

        $users = User::query()->assignable()
            ->when($options['user'] ?? null, fn ($q, $who) => $q->where(fn ($w) => $w->where('username', $who)->orWhere('email', $who)->orWhere('id', $who)))
            ->orderBy('name')
            ->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = $this->sendTo($user, $dryRun, $force);
        }

        return $results;
    }

    /** @return array{user: string, status: string, title?: string, message?: string, lines?: list<string>} */
    public function sendTo(User $user, bool $dryRun = false, bool $force = false): array
    {
        if (! $force && $user->notifications()->where('type', NotificationType::DailyDigest->value)->where('created_at', '>=', today())->exists()) {
            return ['user' => $user->username, 'status' => 'already sent today'];
        }

        $work = $this->workloadFor($user);
        $quiet = $work['open_total'] === 0;
        if ($quiet && ! Settings::bool('digest.include_quiet_days') && ! $force) {
            return ['user' => $user->username, 'status' => 'skipped (nothing open)'];
        }

        [$title, $message, $lines] = $this->compose($user, $work);

        if (! $dryRun) {
            $this->notifier->dailyDigest($user, $title, $message, $lines);
        }

        return ['user' => $user->username, 'status' => $dryRun ? 'dry run' : 'sent', 'title' => $title, 'message' => $message, 'lines' => $lines];
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    public function compose(User $user, array $work): array
    {
        $overdue = $work['overdue']->count();
        $today = $work['due_today']->count();
        $soon = $work['due_soon']->count();

        $title = 'Good morning, '.$user->firstName().' — your briefing for '.today()->format('l, M j');

        $parts = [];
        if ($overdue) {
            $parts[] = "{$overdue} overdue";
        }
        if ($today) {
            $parts[] = "{$today} due today";
        }
        if ($soon) {
            $parts[] = "{$soon} due in the next ".Settings::int('deadline.due_soon_days').' days';
        }

        $message = match (true) {
            $work['open_total'] === 0 => 'You have no open tasks. A clear day!',
            $parts === [] => "You have {$work['open_total']} open ".str('task')->plural($work['open_total']).', none due soon.',
            default => "You have {$work['open_total']} open ".str('task')->plural($work['open_total']).': '.implode(', ', $parts).'.',
        };

        if ($work['completed_yesterday'] > 0) {
            $message .= " You completed {$work['completed_yesterday']} yesterday — nice work.";
        }

        $line = fn (Task $t, string $prefix) => "{$prefix}: {$t->title}".($t->project ? " ({$t->project->name})" : '');
        $lines = [
            ...$work['overdue']->take(5)->map(fn ($t) => $line($t, 'Overdue')),
            ...$work['due_today']->take(5)->map(fn ($t) => $line($t, 'Due today')),
            ...$work['due_soon']->take(5)->map(fn ($t) => $line($t, 'Due '.$t->due_date->format('M j'))),
        ];

        return [$title, $message, $lines];
    }
}
