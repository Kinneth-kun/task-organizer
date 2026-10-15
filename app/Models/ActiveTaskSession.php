<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Currently working on": one live session per person, expiring after a working day.
 */
class ActiveTaskSession extends Model
{
    protected $fillable = ['user_id', 'task_id', 'started_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('active_task_sessions.expires_at', '>', now());
    }
}
