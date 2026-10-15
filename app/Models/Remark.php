<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A progress note on a task. Supervisors mark it reviewed; proof-of-work files attach to it.
 */
class Remark extends Model
{
    protected $fillable = ['task_id', 'user_id', 'body', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Proof of work. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->oldest('id');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('remarks.reviewed_at');
    }
}
