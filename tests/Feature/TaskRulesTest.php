<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskUpdateType;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\Settings;
use App\Support\TaskTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class TaskRulesTest extends TestCase
{
    use RefreshDatabase;

    private TaskService $tasks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasks = app(TaskService::class);
    }

    private function project(array $attributes = [])
    {
        $owner = $attributes['owner'] ?? $this->person();
        unset($attributes['owner']);

        return app(ProjectService::class)->create(['name' => 'Website', ...$attributes], $owner);
    }

    public function test_progress_and_status_stay_in_sync(): void
    {
        $this->assertSame([TaskStatus::Pending, 0], TaskService::normalize(TaskStatus::Pending, 40));
        $this->assertSame([TaskStatus::Completed, 100], TaskService::normalize(TaskStatus::Completed, 10));
        $this->assertSame([TaskStatus::InProgress, 1], TaskService::normalize(TaskStatus::InProgress, 0));
        $this->assertSame([TaskStatus::InProgress, 99], TaskService::normalize(TaskStatus::InProgress, 100));

        $this->assertSame([TaskStatus::InProgress, 40], TaskService::resolve(TaskStatus::Pending, 0, null, 40));
        $this->assertSame([TaskStatus::Completed, 100], TaskService::resolve(TaskStatus::InProgress, 50, null, 100));
        $this->assertSame([TaskStatus::Pending, 0], TaskService::resolve(TaskStatus::InProgress, 50, null, 0));
        $this->assertSame([TaskStatus::OnHold, 60], TaskService::resolve(TaskStatus::OnHold, 50, null, 60));
        $this->assertSame([TaskStatus::Completed, 100], TaskService::resolve(TaskStatus::Delayed, 50, null, 100));
    }

    public function test_a_new_task_defaults_to_its_creator_and_takes_the_assignees_department(): void
    {
        $it = $this->department('IT');
        $hr = $this->department('HR');
        $creator = $this->person(UserRole::Member, $it);
        $assignee = $this->person(UserRole::Member, $hr);

        $mine = $this->tasks->create(['title' => 'Mine'], $creator);
        $this->assertSame($creator->id, $mine->assignee_id);
        $this->assertSame($it->id, $mine->department_id);
        $this->assertNull($mine->project_id);

        $theirs = $this->tasks->create(['title' => 'Theirs', 'assignee_id' => $assignee->id], $creator);
        $this->assertSame($hr->id, $theirs->department_id);

        $this->tasks->updateDetails($theirs, ['assignee_id' => $creator->id], $creator);
        $this->assertSame($it->id, $theirs->refresh()->department_id);
    }

    public function test_administrators_and_executives_cannot_be_assigned(): void
    {
        $creator = $this->person();
        $executive = $this->person(UserRole::Executive);

        $this->expectException(ValidationException::class);
        $this->tasks->create(['title' => 'Nope', 'assignee_id' => $executive->id], $creator);
    }

    public function test_interns_can_only_assign_themselves(): void
    {
        $intern = $this->person(UserRole::Intern);
        $other = $this->person();

        $own = $this->tasks->create(['title' => 'Learn the stack'], $intern);
        $this->assertSame($intern->id, $own->assignee_id);

        $this->expectException(ValidationException::class);
        $this->tasks->create(['title' => 'Delegate', 'assignee_id' => $other->id], $intern);
    }

    public function test_progress_rolls_up_through_three_levels_to_the_project(): void
    {
        $owner = $this->person();
        $project = $this->project(['owner' => $owner]);

        $task = $this->tasks->create(['project_id' => $project->id, 'title' => 'Build'], $owner);
        $other = $this->tasks->create(['project_id' => $project->id, 'title' => 'Launch'], $owner);
        $sub = $this->tasks->create(['parent_id' => $task->id, 'title' => 'API'], $owner);
        $leafA = $this->tasks->create(['parent_id' => $sub->id, 'title' => 'Endpoints'], $owner);
        $leafB = $this->tasks->create(['parent_id' => $sub->id, 'title' => 'Validation'], $owner);

        $this->assertSame(2, $leafA->depth);
        $this->assertSame($project->id, $leafA->project_id, 'subtasks stay in their parent\'s project');

        $this->tasks->applyUpdate($leafA, $owner, null, 100);
        $this->tasks->applyUpdate($leafB, $owner, null, 50);

        $this->assertSame(75, $sub->refresh()->progress);
        $this->assertSame(TaskStatus::InProgress, $sub->status);
        $this->assertSame(75, $task->refresh()->progress);
        $this->assertSame(38, $project->refresh()->progress, 'average of 75 and 0, rounded');

        $this->tasks->applyUpdate($leafB, $owner, 'completed');
        $this->assertSame(TaskStatus::Completed, $sub->refresh()->status);
        $this->assertSame(TaskStatus::Completed, $task->refresh()->status);
        $this->assertSame(TaskUpdateType::Rollup, $task->updates()->first()->type);
        $this->assertSame(50, $project->refresh()->progress);

        $this->assertSame(['1', '1.1', '1.1.1', '1.1.2', '2'], array_values(array_intersect_key(
            TaskTree::numbersForProject($project->id),
            array_flip([$task->id, $sub->id, $leafA->id, $leafB->id, $other->id]),
        )));
    }

    public function test_subtasks_nest_at_most_three_levels(): void
    {
        $owner = $this->person();
        $a = $this->tasks->create(['title' => 'A'], $owner);
        $b = $this->tasks->create(['parent_id' => $a->id, 'title' => 'B'], $owner);
        $c = $this->tasks->create(['parent_id' => $b->id, 'title' => 'C'], $owner);

        $this->assertFalse($c->canHaveChildren());
        $this->expectException(ValidationException::class);
        $this->tasks->create(['parent_id' => $c->id, 'title' => 'D'], $owner);
    }

    public function test_a_parent_follows_its_subtasks_and_ignores_direct_status_changes(): void
    {
        $owner = $this->person();
        $parent = $this->tasks->create(['title' => 'Parent'], $owner);
        $this->tasks->create(['parent_id' => $parent->id, 'title' => 'Child'], $owner);

        $this->tasks->applyUpdate($parent->refresh(), $owner, 'completed', 100, 'Trying to close it');

        $this->assertSame(TaskStatus::Pending, $parent->refresh()->status);
        $this->assertSame('Trying to close it', $parent->latest_remark, 'the remark still applies');
    }

    public function test_overdue_tasks_are_delayed_and_stay_delayed_until_rescheduled(): void
    {
        $owner = $this->person();
        $task = $this->tasks->create(['title' => 'Late', 'due_date' => today()->subDay()->toDateString()], $owner);

        $this->assertSame(TaskStatus::Delayed, $task->status);
        $this->assertSame(TaskUpdateType::AutoDelayed, $task->updates()->first()->type);

        // Choosing In Progress on an overdue task keeps it Delayed.
        $update = $this->tasks->applyUpdate($task, $owner, 'in_progress', 30);
        $this->assertSame(TaskStatus::Delayed, $task->refresh()->status);
        $this->assertSame(30, $task->progress);
        $this->assertSame('in_progress', $update->meta['requested_status']);

        $this->tasks->updateDetails($task, ['due_date' => today()->addWeek()->toDateString()], $owner);
        $this->assertSame(TaskStatus::InProgress, $task->refresh()->status);
        $this->assertNull($task->delayed_at);
    }

    public function test_auto_delay_can_be_switched_off(): void
    {
        Settings::set(['deadline.auto_delay_enabled' => false]);
        $task = $this->tasks->create(['title' => 'Late', 'due_date' => today()->subDay()->toDateString()], $this->person());

        $this->assertSame(TaskStatus::Pending, $task->status);
    }

    public function test_history_is_append_only(): void
    {
        $owner = $this->person();
        $task = $this->tasks->create(['title' => 'Audit me'], $owner);
        $this->tasks->applyUpdate($task, $owner, null, 20, 'Started');

        $this->assertSame(2, $task->updates()->count());

        $entry = TaskUpdate::first();
        try {
            $entry->update(['remark' => 'rewritten']);
            $this->fail('The model allowed an update.');
        } catch (LogicException) {
        }

        try {
            DB::table('task_updates')->where('id', $entry->id)->update(['remark' => 'rewritten']);
            $this->fail('The database allowed an update.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::table('task_updates')->where('id', $entry->id)->delete();
            $this->fail('The database allowed a delete.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_remarks_are_recorded_for_review(): void
    {
        $owner = $this->person();
        $task = $this->tasks->create(['title' => 'Report'], $owner);

        $update = $this->tasks->applyUpdate($task, $owner, null, null, 'Half way');

        $this->assertSame(TaskUpdateType::Remark, $update->type);
        $remark = $task->remarks()->first();
        $this->assertSame('Half way', $remark->body);
        $this->assertFalse($remark->isReviewed());
        $this->assertSame($remark->id, $update->meta['remark_id']);
        $this->assertNull($this->tasks->applyUpdate($task, $owner, 'pending', 0, ''), 'no-op updates record nothing');
    }

    public function test_post_launch_work_does_not_change_a_completed_projects_progress(): void
    {
        $owner = $this->person();
        $project = $this->project(['owner' => $owner, 'tasks' => [['title' => 'Build it']]]);
        $this->tasks->applyUpdate($project->tasks()->first(), $owner, 'completed');
        app(ProjectService::class)->complete($project->refresh(), $owner);

        $fix = $this->tasks->create(['project_id' => $project->id, 'title' => 'Fix typo', 'category' => 'bug_fix'], $owner);
        $default = $this->tasks->create(['project_id' => $project->id, 'title' => 'Nice to have'], $owner);

        $this->assertSame('bug_fix', $fix->category->value);
        $this->assertSame('enhancement', $default->category->value);
        $this->assertSame(100, $project->refresh()->progress);
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertSame('completed', $project->health->value);
    }

    public function test_deleting_a_task_removes_its_subtree_and_rolls_up(): void
    {
        $owner = $this->person();
        $project = $this->project(['owner' => $owner]);
        $parent = $this->tasks->create(['project_id' => $project->id, 'title' => 'Parent'], $owner);
        $keep = $this->tasks->create(['parent_id' => $parent->id, 'title' => 'Keep'], $owner);
        $drop = $this->tasks->create(['parent_id' => $parent->id, 'title' => 'Drop'], $owner);
        $grandchild = $this->tasks->create(['parent_id' => $drop->id, 'title' => 'Grandchild'], $owner);
        $this->tasks->applyUpdate($keep, $owner, 'completed');
        $this->assertSame(50, $parent->refresh()->progress);

        $this->tasks->delete($drop->refresh(), $owner);

        $this->assertSoftDeleted($drop);
        $this->assertSoftDeleted($grandchild);
        $this->assertSame(100, $parent->refresh()->progress);
        $this->assertSame(TaskStatus::Completed, $parent->status);
        $this->assertSame(1, TaskUpdate::where('task_id', $drop->id)->count(), 'history survives');
    }

    public function test_the_assignee_is_never_also_a_collaborator(): void
    {
        $owner = $this->person();
        $helper = $this->person();
        $task = $this->tasks->create(['title' => 'Pair up', 'collaborator_ids' => [$helper->id]], $owner);
        $this->assertTrue($task->isCollaborator($helper));

        $this->assertFalse($this->tasks->addCollaborator($task, $owner->id, $owner), 'the assignee is not added');

        $this->tasks->updateDetails($task, ['assignee_id' => $helper->id], $owner);
        $this->assertFalse($task->refresh()->isCollaborator($helper));
    }

    public function test_deleting_a_project_soft_deletes_its_tasks(): void
    {
        $owner = $this->person();
        $project = $this->project(['owner' => $owner, 'tasks' => [['title' => 'One', 'subtasks' => ['Sub']]]]);

        app(ProjectService::class)->delete($project, $owner);

        $this->assertSoftDeleted($project);
        $this->assertSame(0, Task::where('project_id', $project->id)->count());
        $this->assertSame(2, Task::withTrashed()->where('project_id', $project->id)->count());
    }

    public function test_project_create_builds_team_tasks_and_subtasks(): void
    {
        $owner = $this->person(UserRole::Manager);
        $member = $this->person();
        $executive = $this->person(UserRole::Executive);

        $project = $this->project([
            'owner' => $owner,
            'members' => [['user_id' => $member->id, 'role' => 'manager'], ['user_id' => $executive->id]],
            'tasks' => [['title' => 'Design', 'subtasks' => ['Wireframes', 'Mockups']], ['title' => '  ']],
        ]);

        $this->assertTrue($project->isProjectManager($member));
        $this->assertFalse($project->isMember($executive), 'executives are never members');
        $this->assertSame(3, $project->tasks()->count());
        $this->assertSame(2, Task::where('title', 'Design')->first()->children()->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id, 'type' => 'project_member_added']);
    }
}
