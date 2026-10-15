<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hides a task from one person's Focus of the Day for one date.
 */
class FocusDismissal extends Model
{
    protected $fillable = ['user_id', 'task_id', 'date'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
