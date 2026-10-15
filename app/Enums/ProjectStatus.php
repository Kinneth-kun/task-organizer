<?php

namespace App\Enums;

enum ProjectStatus: string
{
    use HasOptions;

    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::OnHold => 'amber',
            self::Completed => 'emerald',
            self::Cancelled => 'zinc',
        };
    }
}
