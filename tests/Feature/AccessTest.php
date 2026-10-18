<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who can see and change what — including department walls, department supervisors and interns.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $itMember;

    private User $hrMember;

    private User $itManager;

    private User $hrManager;

    private User $intern;

    private User $outsider;

    private User $executive;

    private User $admin;

    private Project $project;

    private Task $itTask;

    private Task $hrTask;

    private Task $internTask;

    protected function setUp(): void
    {
        parent::setUp();

        $it = $this->department('IT');
        $hr = $this->department('HR');

        $this->owner = $this->person(UserRole::Member, $it);
        $this->itMember = $this->person(UserRole::Member, $it);
        $this->hrMember = $this->person(UserRole::Member, $hr);
        $this->itManager = $this->person(UserRole::Manager, $it);
        $this->hrManager = $this->person(UserRole::Manager, $hr);
        $this->intern = $this->person(UserRole::Intern, $it);
        $this->outsider = $this->person(UserRole::Member, $it);
        $this->executive = $this->person(UserRole::Executive, $it);
        $this->admin = $this->person(UserRole::Admin, $it);

        $this->project = app(ProjectService::class)->create([
            'name' => 'Portal',
            'department_id' => $it->id,
            'members' => [['user_id' => $this->itMember->id], ['user_id' => $this->hrMember->id], ['user_id' => $this->intern->id]],
        ], $this->owner);

        $tasks = app(TaskService::class);
        $this->itTask = $tasks->create(['project_id' => $this->project->id, 'title' => 'IT work', 'assignee_id' => $this->owner->id], $this->owner);
        $this->hrTask = $tasks->create(['project_id' => $this->project->id, 'title' => 'HR work', 'assignee_id' => $this->hrMember->id], $this->owner);
        $this->internTask = $tasks->create(['project_id' => $this->project->id, 'title' => 'Intern work', 'assignee_id' => $this->intern->id], $this->owner);
    }

    private function visibleTitles(User $user): array
    {
        return Task::query()->visibleTo($user)->orderBy('title')->pluck('title')->all();
    }

    public function test_project_visibility(): void
    {
        foreach ([$this->owner, $this->itMember, $this->hrMember, $this->intern, $this->itManager, $this->executive, $this->admin] as $user) {
            $this->assertTrue($user->can('view', $this->project), $user->role->value.' should see the project');
            $this->assertContains($this->project->id, Project::query()->visibleTo($user)->pluck('id'));
        }

        foreach ([$this->outsider, $this->hrManager] as $user) {
            $this->assertFalse($user->can('view', $this->project));
            $this->assertNotContains($this->project->id, Project::query()->visibleTo($user)->pluck('id'));
        }
    }

    public function test_department_walls_inside_a_project(): void
    {
        // IT members see IT tasks in their project, but not HR's.
        $this->assertEqualsCanonicalizing(['Intern work', 'IT work'], $this->visibleTitles($this->itMember));
        // HR's member sees only their own task (assigned).
        $this->assertSame(['HR work'], $this->visibleTitles($this->hrMember));
        // Policies agree with the scope.
        $this->assertTrue($this->itMember->can('view', $this->itTask));
        $this->assertFalse($this->itMember->can('view', $this->hrTask));
    }

    public function test_department_managers_see_their_whole_department(): void
    {
        $standalone = app(TaskService::class)->create(['title' => 'IT standalone'], $this->outsider);

        $this->assertEqualsCanonicalizing(['Intern work', 'IT standalone', 'IT work'], $this->visibleTitles($this->itManager));
        $this->assertTrue($this->itManager->can('update', $this->itTask));
        $this->assertTrue($this->itManager->can('edit', $standalone));
        $this->assertTrue($this->itManager->can('reviewRemarks', $this->itTask));
        $this->assertTrue($this->itManager->can('update', $this->project), 'manages the department\'s projects');

        $this->assertSame(['HR work'], $this->visibleTitles($this->hrManager));
        $this->assertFalse($this->hrManager->can('update', $this->project));
    }

    public function test_interns_only_reach_their_own_tasks(): void
    {
        $this->assertSame(['Intern work'], $this->visibleTitles($this->intern));
        $this->assertTrue($this->intern->can('update', $this->internTask));
        $this->assertFalse($this->intern->can('edit', $this->internTask), 'interns update progress but don\'t edit details they didn\'t create');
        $this->assertFalse($this->intern->can('create', Project::class));
        $this->assertFalse($this->intern->can('createTask', $this->project));
        $this->assertFalse($this->intern->can('addSubtask', $this->internTask));
    }

    public function test_executives_see_everything_but_only_change_their_own_work(): void
    {
        $this->assertCount(3, $this->visibleTitles($this->executive));
        $this->assertTrue($this->executive->can('view', $this->hrTask));
        $this->assertFalse($this->executive->can('update', $this->hrTask));
        $this->assertTrue($this->executive->can('reviewRemarks', $this->hrTask));
        $this->assertTrue($this->executive->can('update', $this->project));
        $this->assertFalse($this->executive->can('admin'));
    }

    public function test_administrators_can_do_everything(): void
    {
        foreach (['view', 'update', 'edit', 'delete'] as $ability) {
            $this->assertTrue($this->admin->can($ability, $this->hrTask));
        }
        $this->assertTrue($this->admin->can('delete', $this->project));
        $this->assertTrue($this->admin->can('admin'));
    }

    public function test_project_roles(): void
    {
        $this->assertTrue($this->owner->can('delete', $this->project));
        $this->assertFalse($this->itMember->can('update', $this->project));

        app(ProjectService::class)->changeMemberRole($this->project, $this->itMember->id, 'manager', $this->owner);
        $this->project->refresh();

        $this->assertTrue($this->itMember->can('update', $this->project));
        $this->assertTrue($this->itMember->can('manageMembers', $this->project));
        $this->assertFalse($this->itMember->can('delete', $this->project), 'only the owner deletes');
        $this->assertTrue($this->itMember->can('edit', $this->itTask->fresh()), 'project managers manage their department\'s tasks');
        $this->assertFalse($this->itMember->can('edit', $this->hrTask->fresh()), '…but not another department\'s');
    }

    public function test_collaborators_can_reach_tasks_across_department_walls(): void
    {
        app(TaskService::class)->addCollaborator($this->hrTask, $this->outsider->id, $this->owner);

        $this->assertTrue($this->outsider->can('view', $this->hrTask->fresh()));
        $this->assertTrue($this->outsider->can('update', $this->hrTask->fresh()));
        $this->assertFalse($this->outsider->can('view', $this->project), 'a collaborator does not join the project');
    }

    public function test_remark_review_rules(): void
    {
        app(TaskService::class)->applyUpdate($this->itTask, $this->owner, null, 10, 'Progress note');
        $remark = $this->itTask->remarks()->first();

        $this->assertFalse($this->owner->can('review', $remark), 'nobody reviews their own remark');
        $this->assertFalse($this->itMember->can('review', $remark));
        $this->assertTrue($this->itManager->can('review', $remark));
        $this->assertTrue($this->executive->can('review', $remark));
    }
}
