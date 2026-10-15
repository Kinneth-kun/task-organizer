<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A file on a task, comment, remark (proof of work) or project suggestion. Served only through
 * AttachmentController, which checks the viewer may open the parent record.
 */
class Attachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'user_id', 'original_name', 'path', 'disk', 'mime_type', 'size', 'description'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), config('organizer.attachments.images'), true);
    }

    public function isPdf(): bool
    {
        return $this->extension() === 'pdf';
    }

    public function humanSize(): string
    {
        // Plain formatting: Number::fileSize() needs the intl extension, which many PHP installs lack.
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (int) $size : number_format($size, 1)).' '.$units[$unit];
    }

    public function url(bool $download = false): string
    {
        return route('attachments.show', ['attachment' => $this, 'download' => $download ? 1 : null]);
    }

    /** The task this file ultimately belongs to (null for project suggestions). */
    public function task(): ?Task
    {
        $parent = $this->attachable;

        return match (true) {
            $parent instanceof Task => $parent,
            $parent instanceof TaskComment, $parent instanceof Remark => $parent->task,
            default => null,
        };
    }
}
