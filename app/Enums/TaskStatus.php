<?php

namespace App\Enums;

enum TaskStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InProgress => 'blue',
            self::Completed => 'emerald',
        };
    }

    /** Open work: can still become overdue or due soon. */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Pending, self::InProgress];
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
