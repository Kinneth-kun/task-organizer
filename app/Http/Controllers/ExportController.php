<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Support\DailyReport;
use App\Support\TaskFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports. Each export contains only what the person could see on screen.
 */
class ExportController extends Controller
{
    public function tasks(Request $request): StreamedResponse
    {
        $user = $request->user();
        $query = TaskFilters::apply(Task::query()->visibleTo($user), $request->query(), $user)
            ->with(['project', 'assignee', 'department', 'parent'])
            ->orderBy('due_date')
            ->orderBy('id');

        return $this->csv('tasks-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Title', 'Project', 'Parent task', 'Status', 'Progress %', 'Priority', 'Type', 'Assignee', 'Department', 'Start date', 'Due date', 'Completed at', 'Latest remark',
        ], function ($out) use ($query) {
            $query->chunk(500, function ($tasks) use ($out) {
                foreach ($tasks as $task) {
                    $this->row($out, [
                        $task->id,
                        $task->title,
                        $task->project?->name ?? 'Standalone',
                        $task->parent?->title,
                        $task->status->label(),
                        $task->progress,
                        $task->priority->label(),
                        $task->category?->label(),
                        $task->assignee?->name,
                        $task->department?->name,
                        $task->start_date?->toDateString(),
                        $task->due_date?->toDateString(),
                        $task->completed_at?->toDateTimeString(),
                        $task->latest_remark,
                    ]);
                }
            });
        });
    }

    public function projects(Request $request): StreamedResponse
    {
        $query = Project::query()->visibleTo($request->user())
            ->with(['owner', 'department'])
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()])
            ->orderBy('name');

        return $this->csv('projects-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Project', 'Status', 'Health', 'Progress %', 'Priority', 'Owner', 'Department', 'Start date', 'Due date', 'Open tasks', 'Completed at',
        ], function ($out) use ($query) {
            foreach ($query->cursor() as $project) {
                $this->row($out, [
                    $project->id,
                    $project->name,
                    $project->status->label(),
                    $project->health->label(),
                    $project->progress,
                    $project->priority->label(),
                    $project->owner?->name,
                    $project->department?->name,
                    $project->start_date?->toDateString(),
                    $project->due_date?->toDateString(),
                    $project->open_tasks_count,
                    $project->completed_at?->toDateTimeString(),
                ]);
            }
        });
    }

    public function dailyReport(Request $request): StreamedResponse
    {
        $user = $request->user();
        $day = DailyReport::day($request->query('date'));
        $state = array_key_exists((string) $request->query('state'), DailyReport::STATES) ? (string) $request->query('state') : '';
        $department = $user->isSupervisor() && $request->filled('department') ? (int) $request->query('department') : null;

        $remarks = DailyReport::query($user, $day, $department, $state)->get();
        $changes = DailyReport::changes($remarks);
        $remarks = DailyReport::byProject($remarks)->flatten(1);

        return $this->csv('daily-report-'.$day->toDateString().'.csv', [
            'Time', 'Project', 'Task', 'Parent task', 'Posted by', 'Department', 'Change', 'Update', 'Proof files', 'Reported', 'Reported by', 'Reported at',
        ], function ($out) use ($remarks, $changes) {
            foreach ($remarks as $remark) {
                $this->row($out, [
                    $remark->created_at->format('Y-m-d H:i'),
                    $remark->task?->project?->name ?? DailyReport::STANDALONE,
                    $remark->task?->title,
                    $remark->task?->parent?->title,
                    $remark->user?->name,
                    $remark->task?->department?->name,
                    DailyReport::describeChange($changes->get($remark->id)),
                    $remark->body,
                    $remark->attachments->count(),
                    $remark->isReviewed() ? 'Yes' : 'No',
                    $remark->reviewer?->name,
                    $remark->reviewed_at?->format('Y-m-d H:i'),
                ]);
            }
        });
    }

    /** Writes one row, neutralising spreadsheet formulas (=, +, -, @) in user-entered text. */
    private function row($out, array $values): void
    {
        fputcsv($out, array_map(
            fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value,
            $values,
        ));
    }

    private function csv(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, $header);
            $rows($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
