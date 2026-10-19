<?php

namespace App\Casts;

use App\Enums\LegacyTaskStatus;
use App\Enums\TaskStatus;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** A status in task history: a current TaskStatus, or a LegacyTaskStatus from older entries. */
class HistoryStatus implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): TaskStatus|LegacyTaskStatus|null
    {
        if ($value === null) {
            return null;
        }

        return TaskStatus::tryFrom($value) ?? LegacyTaskStatus::tryFrom($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
