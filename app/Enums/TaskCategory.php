<?php

namespace App\Enums;

/**
 * Post-launch work logged on a completed project. A task without a category is a build task.
 */
enum TaskCategory: string
{
    use HasOptions;

    case Enhancement = 'enhancement';
    case BugFix = 'bug_fix';
    case Update = 'update';

    public function label(): string
    {
        return match ($this) {
            self::Enhancement => 'Enhancement',
            self::BugFix => 'Bug fix',
            self::Update => 'Update',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Enhancement => 'violet',
            self::BugFix => 'rose',
            self::Update => 'sky',
        };
    }
}
