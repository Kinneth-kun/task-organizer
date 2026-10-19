<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * System settings with typed defaults, edited by administrators on the Settings page.
 *
 *     Settings::int('deadline.due_soon_days')
 *     Settings::set(['health.at_risk_delayed_percent' => 10])
 */
class Settings
{
    private const CACHE_KEY = 'organizer.settings';

    /**
     * @var array<string, array{default: string|int|bool, type: string, group: string, label: string, help: string, min?: int, max?: int}>
     */
    public const DEFINITIONS = [
        'general.app_name' => ['default' => 'Task Organizer', 'type' => 'string', 'group' => 'General', 'label' => 'Application name', 'help' => 'Shown in the sidebar and browser title.'],
        'general.organization' => ['default' => 'My Organization', 'type' => 'string', 'group' => 'General', 'label' => 'Organization name', 'help' => 'Shown on the executive dashboard and in meeting mode.'],

        'deadline.due_soon_days' => ['default' => 3, 'type' => 'int', 'min' => 1, 'max' => 30, 'group' => 'Deadlines', 'label' => '"Due soon" window (days)', 'help' => 'Open tasks due within this many days are flagged as due soon and trigger a reminder.'],

        'health.at_risk_delayed_percent' => ['default' => 1, 'type' => 'int', 'min' => 1, 'max' => 100, 'group' => 'Project health', 'label' => 'At Risk when overdue tasks ≥ (%)', 'help' => 'Share of open tasks that are overdue before a project is At Risk. 1 = any overdue task.'],
        'health.delayed_delayed_percent' => ['default' => 25, 'type' => 'int', 'min' => 1, 'max' => 100, 'group' => 'Project health', 'label' => 'Delayed when overdue tasks ≥ (%)', 'help' => 'Share of open tasks that are overdue before a project is Delayed.'],
        'health.progress_gap_percent' => ['default' => 20, 'type' => 'int', 'min' => 5, 'max' => 100, 'group' => 'Project health', 'label' => 'At Risk when behind schedule by ≥ (points)', 'help' => 'Compares actual progress with the progress expected from the time elapsed between start and due date.'],
        'health.due_soon_progress_percent' => ['default' => 75, 'type' => 'int', 'min' => 0, 'max' => 100, 'group' => 'Project health', 'label' => 'At Risk when due soon and progress below (%)', 'help' => 'A project inside the due-soon window with progress below this is At Risk.'],
        'health.overdue_project_is_delayed' => ['default' => true, 'type' => 'bool', 'group' => 'Project health', 'label' => 'A project past its due date is Delayed', 'help' => 'An unfinished project past its own due date is always Delayed.'],

        'digest.enabled' => ['default' => true, 'type' => 'bool', 'group' => 'Daily briefing', 'label' => 'Send the 8:00 am briefing', 'help' => 'A summary of overdue, due-today and upcoming work, every weekday morning.'],
        'digest.include_quiet_days' => ['default' => false, 'type' => 'bool', 'group' => 'Daily briefing', 'label' => 'Send even when there is nothing open', 'help' => 'Off by default, so a clear day produces no notification.'],

        'security.max_login_attempts' => ['default' => 5, 'type' => 'int', 'min' => 3, 'max' => 20, 'group' => 'Security', 'label' => 'Failed sign-ins before lockout', 'help' => 'Consecutive failed sign-ins before the account is temporarily locked.'],
        'security.lockout_minutes' => ['default' => 15, 'type' => 'int', 'min' => 1, 'max' => 1440, 'group' => 'Security', 'label' => 'Lockout duration (minutes)', 'help' => 'How long a locked account stays locked. Administrators can unlock it sooner.'],
    ];

    /**
     * Values memoised for the current application instance (a request, a command or a test).
     *
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    private static ?object $memoApp = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$memo !== null && self::$memoApp === app()) {
            return self::$memo;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];
            } catch (Throwable) {
                return [];
            }
        });

        $values = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored)
                ? self::cast($stored[$key], $definition['type'])
                : $definition['default'];
        }

        self::$memoApp = app();

        return self::$memo = $values;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    /** @param array<string, mixed> $values */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? null;
            if (! $definition) {
                continue;
            }

            $stored = $definition['type'] === 'bool' ? ($value ? '1' : '0') : (string) $value;
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, list<array<string, mixed>>> group => fields, for the settings screen
     */
    public static function grouped(): array
    {
        $values = self::all();
        $groups = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $groups[$definition['group']][] = [...$definition, 'key' => $key, 'value' => $values[$key]];
        }

        return $groups;
    }

    private static function cast(?string $value, string $type): string|int|bool
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true),
            default => (string) $value,
        };
    }
}
