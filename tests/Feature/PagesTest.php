<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use App\Models\User;
use App\Services\CollaborationService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every screen renders for every role, on a small but realistic workspace.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['organizer.admin.password' => 'Admin-pass-123']);
        $this->seed(DatabaseSeeder::class);

        $this->buildWorkspace();
    }

    /**
     * A small workspace built through the services, so history, roll-ups and health are real.
     */
    private function buildWorkspace(): void
    {
        $projects = app(ProjectService::class);
        $tasks = app(TaskService::class);
        $collaboration = app(CollaborationService::class);

        $person = fn (string $username, UserRole $role, string $code) => $this->person($role, $this->department($code), ['username' => $username]);

        $this->person(UserRole::Executive, $this->department('EXEC'), ['username' => 'victoria.santos']);
        $lead = $person('jamie.reyes', UserRole::Manager, 'IT');
        $dev = $person('miguel.tan', UserRole::Member, 'IT');
        $intern = $person('carlo.lim', UserRole::Intern, 'IT');
        $hr = $person('rina.flores', UserRole::Member, 'HR');

        $portal = $projects->create([
            'name' => 'Self-service portal',
            'owner_id' => $lead->id,
            'department_id' => $lead->department_id,
            'due_date' => today()->addDays(25)->toDateString(),
            'members' => [
                ['user_id' => $dev->id, 'role' => ProjectMemberRole::Manager->value],
                ['user_id' => $intern->id],
            ],
        ], $lead);

        $done = $tasks->create(['project_id' => $portal->id, 'title' => 'Gather requirements', 'assignee_id' => $dev->id], $lead);
        $tasks->applyUpdate($done, $dev, 'completed', 100, 'Signed off.');
        $collaboration->review(Remark::query()->where('task_id', $done->id)->firstOrFail(), $lead);

        $build = $tasks->create(['project_id' => $portal->id, 'title' => 'Build leave module', 'assignee_id' => $dev->id, 'due_date' => today()->addDays(10)->toDateString()], $lead);
        $api = $tasks->create(['parent_id' => $build->id, 'title' => 'Leave request API', 'assignee_id' => $intern->id], $lead);
        $tasks->applyUpdate($api, $intern, null, 60, 'Halfway there. @miguel.tan please review.');
        $tasks->create(['project_id' => $portal->id, 'title' => 'Payslip viewer', 'assignee_id' => $dev->id, 'due_date' => today()->subDays(2)->toDateString()], $lead);

        $collaboration->comment($build, 'Kick-off notes are in the shared drive.', [], $lead);
        $collaboration->startWorking($api, $intern);
        $collaboration->toggleFocus($build, $dev);

        $archive = $projects->create([
            'name' => 'Intranet refresh',
            'owner_id' => $lead->id,
            'department_id' => $lead->department_id,
            'tasks' => [['title' => 'New homepage']],
        ], $lead);
        foreach ($archive->tasks as $task) {
            $tasks->applyUpdate($task, $lead, 'completed', 100);
        }
        $projects->complete($archive->refresh(), $lead);

        // Standalone work in another department, which the IT intern must not see.
        $tasks->create(['title' => 'Prepare onboarding kit', 'assignee_id' => $hr->id, 'due_date' => today()->addDays(6)->toDateString()], $hr);
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
