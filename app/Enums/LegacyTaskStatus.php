<?php

namespace App\Enums;

/**
 * Task statuses that are no longer used. Tasks only ever have a TaskStatus now, but the
 * append-only history still holds entries written while these existed, so they stay readable.
 */
enum LegacyTaskStatus: string
{
    case Delayed = 'delayed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Delayed => 'Delayed',
            self::OnHold => 'On Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delayed => 'red',
            self::OnHold => 'amber',
            self::Cancelled => 'zinc',
        };
    }
}
