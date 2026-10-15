<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
