<?php

namespace App\Enums;

enum ProjectHealth: string
{
    use HasOptions;

    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'On Track',
            self::AtRisk => 'At Risk',
            self::Delayed => 'Delayed',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnTrack => 'emerald',
            self::AtRisk => 'amber',
            self::Delayed => 'red',
            self::Completed => 'indigo',
            self::OnHold => 'slate',
        };
    }

    /** Lower = needs attention sooner (used for sorting). */
    public function urgency(): int
    {
        return match ($this) {
            self::Delayed => 0,
            self::AtRisk => 1,
            self::OnTrack => 2,
            self::OnHold => 3,
            self::Completed => 4,
        };
    }
}
