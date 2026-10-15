<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectSuggestion;
use App\Models\Remark;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Short, stable names in polymorphic columns (attachments, notifications, activity log).
        Relation::enforceMorphMap([
            'user' => User::class,
            'department' => Department::class,
            'project' => Project::class,
            'task' => Task::class,
            'remark' => Remark::class,
            'comment' => TaskComment::class,
            'suggestion' => ProjectSuggestion::class,
            'attachment' => Attachment::class,
        ]);

        // Administrators can do everything.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('full-access', fn (User $user) => $user->hasFullAccess());
        Gate::define('supervise', fn (User $user) => $user->isSupervisor());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Livewire requests re-check the deactivation guard of the page they came from.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
