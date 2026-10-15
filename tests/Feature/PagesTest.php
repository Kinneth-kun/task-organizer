<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every screen renders for every role, on realistic demo data.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['organizer.admin.password' => 'Admin-pass-123']);
        $this->seed(DemoSeeder::class);
    }

    public static function people(): array
    {
        return [
            'administrator' => ['admin'],
            'executive' => ['victoria.santos'],
            'department manager' => ['jamie.reyes'],
            'member' => ['miguel.tan'],
            'intern' => ['carlo.lim'],
        ];
    }

    #[DataProvider('people')]
    public function test_common_pages_render(string $username): void
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        foreach (['dashboard', 'tasks.index', 'projects.index', 'calendar', 'search', 'notifications.index', 'profile'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('tasks.index', ['view' => 'all', 'status' => '']))->assertOk();
        $this->get(route('search', ['q' => 'leave']))->assertOk();
        $this->get(route('calendar', ['month' => now()->addMonth()->format('Y-m')]))->assertOk();
        $this->get(route('tasks.create'))->assertOk();
        $this->get(route('export.tasks'))->assertOk();
        $this->get(route('export.projects'))->assertOk();
    }

    #[DataProvider('people')]
    public function test_visible_tasks_and_projects_render(string $username): void
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        foreach (Task::query()->visibleTo($user)->get() as $task) {
            $this->get(route('tasks.show', $task))->assertOk();
            $this->get(route('tasks.show', ['task' => $task, 'tab' => 'history']))->assertOk();
        }

        foreach (Project::query()->visibleTo($user)->get() as $project) {
            foreach (['tasks', 'board', 'team', 'overview'] as $tab) {
                $this->get(route('projects.show', ['project' => $project, 'tab' => $tab]))->assertOk();
            }
        }
    }

    public function test_hidden_tasks_are_forbidden(): void
    {
        $intern = User::where('username', 'carlo.lim')->firstOrFail();
        $hidden = Task::query()->whereNotIn('id', Task::query()->visibleTo($intern)->select('id'))->firstOrFail();

        $this->actingAs($intern)->get(route('tasks.show', $hidden))->assertForbidden();
    }

    public function test_admin_pages(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);

        foreach (['admin.users.index', 'admin.users.create', 'admin.departments', 'admin.settings', 'admin.activity', 'executive', 'meeting', 'projects.create'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('admin.users.edit', User::where('username', 'miguel.tan')->first()))->assertOk();
        $this->get(route('projects.edit', Project::first()))->assertOk();
        $this->get(route('tasks.edit', Task::first()))->assertOk();
        $this->get(route('executive', ['department' => $admin->department_id]))->assertOk();
    }

    public function test_oversight_pages_are_restricted(): void
    {
        $executive = User::where('username', 'victoria.santos')->firstOrFail();
        $this->actingAs($executive)->get(route('executive'))->assertOk();
        $this->actingAs($executive)->get(route('admin.activity'))->assertOk();
        $this->actingAs($executive)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($executive)->get(route('meeting'))->assertForbidden();

        $member = User::where('username', 'miguel.tan')->firstOrFail();
        foreach (['executive', 'admin.activity', 'admin.users.index', 'admin.settings', 'admin.departments', 'meeting'] as $route) {
            $this->actingAs($member)->get(route($route))->assertForbidden();
        }

        $intern = User::where('username', 'carlo.lim')->firstOrFail();
        $this->actingAs($intern)->get(route('projects.create'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }
}
