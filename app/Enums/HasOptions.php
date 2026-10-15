<?php

namespace App\Enums;

/**
 * Shared helpers for the backed enums: select options, value lists and safe lookups.
 */
trait HasOptions
{
    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function labelFor(?string $value): string
    {
        return $value !== null && ($case = self::tryFrom($value)) ? $case->label() : 'Unknown';
    }
}
