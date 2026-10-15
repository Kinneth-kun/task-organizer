<?php

namespace App\Enums;

enum ProjectMemberRole: string
{
    use HasOptions;

    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Project manager',
            self::Member => 'Member',
        };
    }
}
