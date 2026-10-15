<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\ProjectSuggestion;
use App\Models\User;

class AttachmentPolicy
{
    /** Anyone who can open the record the file belongs to. */
    public function view(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        if ($parent instanceof ProjectSuggestion) {
            return $parent->project !== null && $user->can('view', $parent->project);
        }

        $task = $attachment->task();

        return $task !== null && $user->can('view', $task);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->user_id === $user->id) {
            return true;
        }

        $task = $attachment->task();

        return $task !== null && $user->can('edit', $task);
    }
}
