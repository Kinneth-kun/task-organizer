<?php

namespace App\Support;

/**
 * Tailwind class strings per colour family. Written out in full so Tailwind's scanner finds them
 * (app/ is a @source in resources/css/app.css).
 */
class Palette
{
    /** Colours offered for projects and avatars. */
    public const CHOICES = ['indigo', 'violet', 'sky', 'emerald', 'amber', 'rose', 'teal', 'fuchsia', 'orange', 'cyan'];

    private const BADGE = [
        'slate' => 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-400/30',
        'zinc' => 'bg-zinc-50 text-zinc-700 ring-zinc-600/20 dark:bg-zinc-500/10 dark:text-zinc-300 dark:ring-zinc-400/30',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/30',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-400/30',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-400/30',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/10 dark:text-cyan-300 dark:ring-cyan-400/30',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/30',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/30',
        'fuchsia' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20 dark:bg-fuchsia-500/10 dark:text-fuchsia-300 dark:ring-fuchsia-400/30',
    ];
    private const SOFT = [
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',
        'zinc' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-500/15 dark:text-zinc-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
        'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'fuchsia' => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
    ];
    private const SOLID = [
        'slate' => 'bg-slate-500 text-white',
        'zinc' => 'bg-zinc-500 text-white',
        'red' => 'bg-red-500 text-white',
        'rose' => 'bg-rose-500 text-white',
        'orange' => 'bg-orange-500 text-white',
        'amber' => 'bg-amber-500 text-white',
        'emerald' => 'bg-emerald-500 text-white',
        'teal' => 'bg-teal-500 text-white',
        'cyan' => 'bg-cyan-500 text-white',
        'sky' => 'bg-sky-500 text-white',
        'blue' => 'bg-blue-500 text-white',
        'indigo' => 'bg-indigo-500 text-white',
        'violet' => 'bg-violet-500 text-white',
        'fuchsia' => 'bg-fuchsia-500 text-white',
    ];
    private const DOT = [
        'slate' => 'bg-slate-500',
        'zinc' => 'bg-zinc-500',
        'red' => 'bg-red-500',
        'rose' => 'bg-rose-500',
        'orange' => 'bg-orange-500',
        'amber' => 'bg-amber-500',
        'emerald' => 'bg-emerald-500',
        'teal' => 'bg-teal-500',
        'cyan' => 'bg-cyan-500',
        'sky' => 'bg-sky-500',
        'blue' => 'bg-blue-500',
        'indigo' => 'bg-indigo-500',
        'violet' => 'bg-violet-500',
        'fuchsia' => 'bg-fuchsia-500',
    ];
    private const TEXT = [
        'slate' => 'text-slate-600 dark:text-slate-400',
        'zinc' => 'text-zinc-600 dark:text-zinc-400',
        'red' => 'text-red-600 dark:text-red-400',
        'rose' => 'text-rose-600 dark:text-rose-400',
        'orange' => 'text-orange-600 dark:text-orange-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'emerald' => 'text-emerald-600 dark:text-emerald-400',
        'teal' => 'text-teal-600 dark:text-teal-400',
        'cyan' => 'text-cyan-600 dark:text-cyan-400',
        'sky' => 'text-sky-600 dark:text-sky-400',
        'blue' => 'text-blue-600 dark:text-blue-400',
        'indigo' => 'text-indigo-600 dark:text-indigo-400',
        'violet' => 'text-violet-600 dark:text-violet-400',
        'fuchsia' => 'text-fuchsia-600 dark:text-fuchsia-400',
    ];
    private const BORDER = [
        'slate' => 'border-slate-500',
        'zinc' => 'border-zinc-500',
        'red' => 'border-red-500',
        'rose' => 'border-rose-500',
        'orange' => 'border-orange-500',
        'amber' => 'border-amber-500',
        'emerald' => 'border-emerald-500',
        'teal' => 'border-teal-500',
        'cyan' => 'border-cyan-500',
        'sky' => 'border-sky-500',
        'blue' => 'border-blue-500',
        'indigo' => 'border-indigo-500',
        'violet' => 'border-violet-500',
        'fuchsia' => 'border-fuchsia-500',
    ];

    private static function pick(array $map, ?string $color): string
    {
        return $map[$color] ?? $map['slate'];
    }

    /** Pill badges: statuses, priorities, roles. */
    public static function badge(?string $color): string
    {
        return self::pick(self::BADGE, $color);
    }

    /** Tinted tiles and icon backgrounds. */
    public static function soft(?string $color): string
    {
        return self::pick(self::SOFT, $color);
    }

    /** Avatars and solid chips. */
    public static function solid(?string $color): string
    {
        return self::pick(self::SOLID, $color);
    }

    /** Dots and progress-bar fills. */
    public static function dot(?string $color): string
    {
        return self::pick(self::DOT, $color);
    }

    public static function text(?string $color): string
    {
        return self::pick(self::TEXT, $color);
    }

    public static function border(?string $color): string
    {
        return self::pick(self::BORDER, $color);
    }
}
