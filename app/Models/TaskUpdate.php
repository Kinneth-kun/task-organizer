<?php

namespace App\Models;

use App\Casts\HistoryStatus;
use App\Enums\TaskUpdateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One append-only history entry. The table rejects UPDATE and DELETE (database triggers),
 * and so does the model.
 *
 * @property TaskUpdateType $type
 */
class TaskUpdate extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'type', 'old_status', 'new_status', 'old_progress', 'new_progress', 'remark', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskUpdateType::class,
            'old_status' => HistoryStatus::class,
            'new_status' => HistoryStatus::class,
            'old_progress' => 'integer',
            'new_progress' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Task history is append-only.'));
        static::deleting(fn () => throw new LogicException('Task history is append-only.'));
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->user_id === null || $this->type->isSystem();
    }
}
