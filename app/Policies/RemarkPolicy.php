<?php

namespace App\Policies;

use App\Models\Remark;
use App\Models\User;

class RemarkPolicy
{
    /** Supervisors mark other people's updates as reported in the daily meeting, never their own. */
    public function review(User $user, Remark $remark): bool
    {
        return $remark->user_id !== $user->id
            && ! $remark->isReviewed()
            && $user->can('reviewRemarks', $remark->task);
    }
}
