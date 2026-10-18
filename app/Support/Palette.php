<?php

namespace App\Support;

/**
 * Tailwind class strings for badges, tiles, avatars, dots and progress bars.
 *
 * The system no longer colour-codes statuses, priorities, roles, departments or projects: every
 * method returns the same neutral styling whatever colour is asked for. The colour columns and the
 * enums' color() methods stay so existing data and callers keep working.
 */
class Palette
{
    /** Colours stored on projects and avatars (kept for existing data; no longer offered in forms). */
    public const CHOICES = ['indigo', 'violet', 'sky', 'emerald', 'amber', 'rose', 'teal', 'fuchsia', 'orange', 'cyan'];

    /** Pill badges: statuses, priorities, roles. */
    public static function badge(?string $color): string
    {
        return 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-400/30';
    }

    /** Tinted tiles and icon backgrounds. */
    public static function soft(?string $color): string
    {
        return 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300';
    }

    /** Avatars and solid chips. */
    public static function solid(?string $color): string
    {
        return 'bg-slate-500 text-white';
    }

    /** Dots and progress-bar fills. */
    public static function dot(?string $color): string
    {
        return 'bg-slate-500';
    }

    public static function text(?string $color): string
    {
        return 'text-slate-600 dark:text-slate-400';
    }

    public static function border(?string $color): string
    {
        return 'border-slate-300 dark:border-slate-700';
    }
}
