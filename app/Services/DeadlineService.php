<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Automatic delay detection and deadline reminders.
 *
 * Runs from the scheduler (tasks:check-deadlines) and, so the app stays current where cron is
 * missing, after signed-in requests at most once every 5 minutes.
 */
class DeadlineService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ProjectHealthService $health,
        private readonly Notifier $notifier,
        private readonly CollaborationService $collaboration,
    ) {}

    /** @return array{delayed: int, reminders: int, projects: int, sessions: int} */
    public function run(): array
    {
        return [
            'delayed' => $this->markOverdueTasksDelayed(),
            'reminders' => $this->sendDeadlineReminders(),
            'projects' => $this->health->refreshAll(),
            'sessions' => $this->collaboration->expireSessions(),
        ];
    }

    /** Run at most once per `$seconds` across all processes. */
    public function runThrottled(int $seconds = 300): bool
    {
        if (! Cache::add('organizer.deadline-check', now()->timestamp, $seconds)) {
            return false;
        }

        $this->run();

        return true;
    }

    public function markOverdueTasksDelayed(): int
    {
        if (! Settings::bool('deadline.auto_delay_enabled')) {
            return 0;
        }

        $count = 0;
        Task::query()
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->orderBy('depth', 'desc')
            ->orderBy('id')
            ->each(function (Task $task) use (&$count) {
                if ($this->tasks->syncDelay($task)) {
                    $this->tasks->afterChange($task);
                    $count++;
                }
            });

        return $count;
    }

    /** Remind the assignee and collaborators once per task per due date inside the due-soon window. */
    public function sendDeadlineReminders(): int
    {
        $sent = 0;
        $windowEnd = today()->addDays(Settings::int('deadline.due_soon_days'));

        Task::query()
            ->with(['project'])
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', $windowEnd)
            ->orderBy('id')
            ->each(function (Task $task) use (&$sent) {
                if ($task->deadline_reminded_for?->isSameDay($task->due_date)) {
                    return;
                }

                $this->notifier->deadlineApproaching($task, $task->dueLabel() ?? 'Due soon');
                $task->forceFill(['deadline_reminded_for' => $task->due_date])->saveQuietly();
                $sent++;
            });

        return $sent;
    }
}
