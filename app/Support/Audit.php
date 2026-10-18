<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit trail behind Oversight → Audit trail.
 *
 *     Audit::log('task.created', 'Created task "Brief"', $task);
 *
 * `$action` is a dotted event name (auth.login, task.completed …) used by the log filters.
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], User|false|null $causer = null): ?Activity
    {
        $causer = $causer === false ? null : ($causer ?? auth()->user());
        $request = app()->runningInConsole() ? null : request();

        $logger = activity('audit')
            ->event($action)
            ->withProperties(array_filter([
                ...$properties,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ], fn ($value) => $value !== null));

        if ($subject) {
            $logger->performedOn($subject);
        }

        if ($causer) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
        }

        return $logger->log($description);
    }

    /** The dotted action groups shown in the log filter. */
    public const GROUPS = [
        'auth' => 'Sign-in',
        'user' => 'Users',
        'department' => 'Departments',
        'project' => 'Projects',
        'task' => 'Tasks',
        'remark' => 'Remarks',
        'comment' => 'Comments',
        'attachment' => 'Files',
        'settings' => 'Settings',
    ];
}
