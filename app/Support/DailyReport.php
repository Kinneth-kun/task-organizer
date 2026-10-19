<?php

namespace App\Support;

use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The daily report: every update (a remark posted with a progress update) on a given day, grouped
 * by project, and whether it has been reported in the daily meeting yet. Shared by the Daily
 * report page, its CSV export and the "Latest updates" slide in meeting mode.
 */
class DailyReport
{
    public const STATES = [
        '' => 'All updates',
        'pending' => 'Not reported',
        'reported' => 'Reported',
    ];

    public const STANDALONE = 'Standalone tasks';

    /** The day a ?date= value asks for, never in the future; today when missing or invalid. */
    public static function day(?string $date): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $today;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return $today;
        }

        return $day && $day->lte($today) ? $day : $today;
    }

    /** Where the meeting's "Latest updates" slide starts: the previous working day (Friday on a Monday). */
    public static function meetingSince(): CarbonImmutable
    {
        $day = CarbonImmutable::today()->subDay();
        while ($day->isWeekend()) {
            $day = $day->subDay();
        }

        return $day;
    }

    /**
     * Updates on tasks the person can see, newest first.
     *
     * @param  CarbonInterface|array{0: CarbonInterface, 1: CarbonInterface}  $when  one day, or [from, to] inclusive
     */
    public static function query(User $user, CarbonInterface|array $when, ?int $departmentId = null, string $state = ''): Builder
    {
        [$from, $to] = is_array($when) ? $when : [$when, $when];

        return Remark::query()
            ->with(['user', 'reviewer', 'attachments', 'task' => fn ($q) => $q->with(['project', 'parent', 'assignee', 'department'])])
            ->whereIn('remarks.task_id', self::visibleTaskIds($user, $departmentId))
            ->where('remarks.created_at', '>=', $from->copy()->startOfDay())
            ->where('remarks.created_at', '<=', $to->copy()->endOfDay())
            ->when($state === 'pending', fn ($q) => $q->whereNull('remarks.reviewed_at'))
            ->when($state === 'reported', fn ($q) => $q->whereNotNull('remarks.reviewed_at'))
            ->latest('remarks.created_at')
            ->latest('remarks.id');
    }

    /**
     * Updates from before the given day that still haven't been reported, so a backlog isn't
     * lost when the meeting skips a day.
     *
     * @return array{count: int, oldest: ?CarbonImmutable}
     */
    public static function pendingBefore(User $user, CarbonInterface $day, ?int $departmentId = null): array
    {
        $query = Remark::query()
            ->whereIn('remarks.task_id', self::visibleTaskIds($user, $departmentId))
            ->whereNull('remarks.reviewed_at')
            ->where('remarks.created_at', '<', $day->copy()->startOfDay());

        $oldest = $query->clone()->min('remarks.created_at');

        return ['count' => $query->count(), 'oldest' => $oldest ? CarbonImmutable::parse($oldest)->startOfDay() : null];
    }

    private static function visibleTaskIds(User $user, ?int $departmentId): Builder
    {
        $tasks = Task::query()->visibleTo($user);
        if ($departmentId) {
            $tasks->where('tasks.department_id', $departmentId);
        }

        return $tasks->select('tasks.id');
    }

    /**
     * Groups updates by project (standalone tasks last), keeping each group newest first.
     *
     * @param  Collection<int, Remark>  $remarks
     * @return Collection<string, Collection<int, Remark>>
     */
    public static function byProject(Collection $remarks): Collection
    {
        return $remarks
            ->groupBy(fn (Remark $remark) => $remark->task?->project?->name ?? self::STANDALONE)
            ->sortBy(fn ($group, string $name) => [$name === self::STANDALONE ? 1 : 0, mb_strtolower($name)]);
    }

    /**
     * The history entry each update was posted with, for its status and progress change.
     *
     * @param  Collection<int, Remark>  $remarks
     * @return Collection<int, TaskUpdate> keyed by remark id
     */
    public static function changes(Collection $remarks): Collection
    {
        if ($remarks->isEmpty()) {
            return collect();
        }

        return TaskUpdate::query()
            ->whereIn('task_id', $remarks->pluck('task_id')->unique())
            ->whereIn('meta->remark_id', $remarks->pluck('id'))
            ->get()
            ->keyBy(fn (TaskUpdate $update) => (int) ($update->meta['remark_id'] ?? 0));
    }

    /** "Pending → In Progress · 40% → 70%", or null when the update only added a remark. */
    public static function describeChange(?TaskUpdate $update): ?string
    {
        if (! $update) {
            return null;
        }

        $parts = [];
        if ($update->old_status !== $update->new_status && $update->new_status) {
            $parts[] = ($update->old_status?->label() ?? '—').' → '.$update->new_status->label();
        }
        if ($update->old_progress !== $update->new_progress && $update->new_progress !== null) {
            $parts[] = ($update->old_progress ?? 0).'% → '.$update->new_progress.'%';
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
