<?php

namespace App\Enums;

enum UserRole: string
{
    use HasOptions;

    /** Everything, including users, departments, settings and meeting mode. */
    case Admin = 'admin';
    /** Sees every project and task, the executive dashboard and activity logs. */
    case Executive = 'executive';
    /** Manages every project and task in their own department. */
    case Manager = 'manager';
    /** Their own projects and the tasks they are on. */
    case Member = 'member';
    /** Only the tasks they are on, plus the projects they belong to. */
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Executive => 'Executive',
            self::Manager => 'Department Manager',
            self::Member => 'Member',
            self::Intern => 'Intern',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'rose',
            self::Executive => 'violet',
            self::Manager => 'indigo',
            self::Member => 'sky',
            self::Intern => 'emerald',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full control: users, departments, settings, meeting mode and every project and task.',
            self::Executive => 'Oversight of every project and task, the executive dashboard and activity logs.',
            self::Manager => 'Manages every project and task in their own department and reviews remarks.',
            self::Member => 'Works on projects they belong to and tasks they are assigned to.',
            self::Intern => 'Works only on tasks assigned to them; cannot create projects or assign others.',
        };
    }

    /** Administrators and executives see every project and task. */
    public function hasFullAccess(): bool
    {
        return $this === self::Admin || $this === self::Executive;
    }
}
