<?php

namespace App\Enums;

enum TaskStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delayed = 'delayed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Delayed => 'Delayed',
            self::OnHold => 'On Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InProgress => 'blue',
            self::Completed => 'emerald',
            self::Delayed => 'red',
            self::OnHold => 'amber',
            self::Cancelled => 'zinc',
        };
    }

    /** Open work: can still become delayed or due soon. */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** Statuses the automatic delay check applies to. */
    public function canAutoDelay(): bool
    {
        return $this === self::Pending || $this === self::InProgress;
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Pending, self::InProgress, self::Delayed];
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::open());
    }

    /** 0% Pending, 1–99% In Progress, 100% Completed. */
    public static function fromProgress(int $progress): self
    {
        return match (true) {
            $progress <= 0 => self::Pending,
            $progress >= 100 => self::Completed,
            default => self::InProgress,
        };
    }
}
