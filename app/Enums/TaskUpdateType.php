<?php

namespace App\Enums;

/**
 * Kinds of append-only history entries on a task.
 */
enum TaskUpdateType: string
{
    use HasOptions;

    case Created = 'created';
    case Update = 'update';
    case Remark = 'remark';
    case Assignment = 'assignment';
    case Details = 'details';
    case AutoDelayed = 'auto_delayed';
    case Undelayed = 'undelayed';
    case Rollup = 'rollup';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Update => 'Progress update',
            self::Remark => 'Remark',
            self::Assignment => 'Reassigned',
            self::Details => 'Details changed',
            self::AutoDelayed => 'Automatically marked delayed',
            self::Undelayed => 'Delay cleared',
            self::Rollup => 'Updated from subtasks',
        };
    }

    /** Written by the system rather than a person. */
    public function isSystem(): bool
    {
        return in_array($this, [self::AutoDelayed, self::Undelayed, self::Rollup], true);
    }
}
