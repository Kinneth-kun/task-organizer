<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActiveTaskSession;
use App\Models\Task;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Account administration. Deactivating someone or resetting their password signs them out
 * of every session immediately.
 */
class UserService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): User
    {
        $user = User::create([
            ...$data,
            'username' => strtolower($data['username']),
            'email' => strtolower($data['email']),
        ]);

        Audit::log('user.created', "Created account for {$user->name} ({$user->role->label()})", $user, [], $actor);

        return $user;
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $password = $data['password'] ?? null;
            unset($data['password']);

            if (isset($data['username'])) {
                $data['username'] = strtolower($data['username']);
            }
            if (isset($data['email'])) {
                $data['email'] = strtolower($data['email']);
            }

            $user->fill($data);
            $dirty = array_keys($user->getDirty());
            $user->save();

            if (in_array('department_id', $dirty, true)) {
                $this->syncTaskDepartments($user);
            }

            // Administrators and executives can't hold work; release it.
            if (in_array('role', $dirty, true) && $user->role->hasFullAccess()) {
                Task::query()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
                DB::table('task_collaborators')->where('user_id', $user->id)->delete();
                DB::table('project_members')->where('user_id', $user->id)->delete();
            }

            if ($dirty) {
                Audit::log('user.updated', "Edited {$user->name} (".implode(', ', str_replace('_', ' ', $dirty)).')', $user, ['fields' => $dirty], $actor);
            }

            if ($password) {
                $this->resetPassword($user, $password, $actor);
            }

            return $user;
        });
    }

    public function resetPassword(User $user, string $password, User $actor): void
    {
        $user->forceFill(['password' => $password, 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        $user->revokeSessions();
        Audit::log('user.password_reset', "Reset the password for {$user->name}", $user, [], $actor);
    }

    public function deactivate(User $user, User $actor): void
    {
        $user->forceFill(['is_active' => false])->save();
        $user->revokeSessions();
        ActiveTaskSession::query()->where('user_id', $user->id)->delete();
        Audit::log('user.deactivated', "Deactivated {$user->name}", $user, [], $actor);
    }

    public function activate(User $user, User $actor): void
    {
        $user->forceFill(['is_active' => true, 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        Audit::log('user.activated', "Reactivated {$user->name}", $user, [], $actor);
    }

    public function unlock(User $user, User $actor): void
    {
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        Audit::log('user.unlocked', "Unlocked {$user->name}", $user, [], $actor);
    }

    /** A task's department follows its assignee, else its creator. */
    public function syncTaskDepartments(User $user): void
    {
        Task::withTrashed()->where('assignee_id', $user->id)->update(['department_id' => $user->department_id]);
        Task::withTrashed()->whereNull('assignee_id')->where('created_by', $user->id)->update(['department_id' => $user->department_id]);
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return UserRole::options();
    }
}
