<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\SettingsPage;
use App\Livewire\Admin\UserForm;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Dashboard;
use App\Livewire\Projects\ProjectForm;
use App\Livewire\Projects\ProjectIndex;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\QuickCreate;
use App\Livewire\Tasks\TaskForm;
use App\Livewire\Tasks\TaskShow;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_an_update_with_proof_of_work_and_reviewing_it(): void
    {
        Storage::fake('local');
        $manager = $this->person(UserRole::Manager);
        $member = $this->person();
        $task = app(TaskService::class)->create(['title' => 'Write report', 'assignee_id' => $member->id], $manager);

        Livewire::actingAs($member)->test(TaskShow::class, ['task' => $task])
            ->set('qStatus', 'in_progress')
            ->set('qProgress', 60)
            ->set('qRemark', 'Draft done, see attached.')
            ->set('proof', [UploadedFile::fake()->image('screenshot.png')])
            ->call('saveUpdate')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame(60, $task->progress);
        $remark = $task->remarks()->first();
        $this->assertSame(1, $remark->attachments()->count());
        $file = $remark->attachments()->first();
        Storage::disk('local')->assertExists($file->path);

        // The proof is served only to people who can see the task.
        $this->actingAs($manager)->get($file->url())->assertOk();
        $this->actingAs($this->person(UserRole::Member, $this->department('HR')))->get($file->url())->assertForbidden();

        Livewire::actingAs($manager)->test(TaskShow::class, ['task' => $task])
            ->call('reviewRemark', $remark->id)
            ->assertHasNoErrors();

        $this->assertTrue($remark->refresh()->isReviewed());
        $this->assertSame(1, $member->notifications()->where('type', 'remark_reviewed')->count());
    }

    public function test_disguised_files_are_rejected(): void
    {
        Storage::fake('local');
        $member = $this->person();
        $task = app(TaskService::class)->create(['title' => 'Upload'], $member);

        Livewire::actingAs($member)->test(TaskShow::class, ['task' => $task])
            ->set('files', [UploadedFile::fake()->createWithContent('invoice.pdf', '<?php echo "not a pdf";')])
            ->call('uploadFiles')
            ->assertHasErrors();

        $this->assertSame(0, Attachment::count());
    }

    public function test_comments_with_mentions_and_subtasks_from_the_task_page(): void
    {
        $member = $this->person();
        $colleague = $this->person(UserRole::Manager, attributes: ['username' => 'lead.person']);
        $task = app(TaskService::class)->create(['title' => 'Plan'], $member);

        Livewire::actingAs($member)->test(TaskShow::class, ['task' => $task])
            ->set('commentBody', 'Can you check this @lead.person?')
            ->call('addComment')
            ->assertHasNoErrors()
            ->call('openSubtask')
            ->set('subTitle', 'Step one')
            ->call('addSubtask')
            ->assertHasNoErrors()
            ->call('toggleFocus')
            ->call('startWorking');

        $this->assertSame(1, $task->comments()->count());
        $this->assertSame(1, $colleague->notifications()->where('type', 'mentioned')->count());
        $this->assertSame(1, $task->children()->count());
        $this->assertTrue($member->focusTasks()->whereKey($task->id)->exists());
        $this->assertSame($task->id, $member->activeSession()->first()->task_id);
    }

    public function test_people_without_access_cannot_act_on_a_task(): void
    {
        $owner = $this->person();
        $task = app(TaskService::class)->create(['title' => 'Private'], $owner);
        $stranger = $this->person(UserRole::Member, $this->department('HR'));

        Livewire::actingAs($stranger)->test(TaskShow::class, ['task' => $task])->assertForbidden();
    }

    public function test_kanban_moves_change_status_with_history(): void
    {
        $owner = $this->person();
        $project = app(ProjectService::class)->create(['name' => 'Board', 'tasks' => [['title' => 'Card']]], $owner);
        $card = $project->tasks()->first();

        Livewire::actingAs($owner)->test(ProjectShow::class, ['project' => $project])
            ->set('tab', 'board')
            ->call('moveTask', $card->id, 0, 'in_progress')
            ->assertHasNoErrors();

        $this->assertSame(TaskStatus::InProgress, $card->refresh()->status);
        $this->assertSame(1, $card->progress);
        $this->assertSame('update', $card->updates()->first()->type->value);
    }

    public function test_project_page_actions(): void
    {
        $owner = $this->person();
        $teammate = $this->person();
        $project = app(ProjectService::class)->create(['name' => 'Launch'], $owner);

        Livewire::actingAs($owner)->test(ProjectShow::class, ['project' => $project])
            ->set('newTitle', 'Quick task')
            ->call('addTask')
            ->set('memberId', (string) $teammate->id)
            ->set('memberRole', 'manager')
            ->call('addMember')
            ->call('complete')
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame(1, $project->tasks()->count());
        $this->assertTrue($project->isProjectManager($teammate));
        $this->assertTrue($project->isCompleted());

        Livewire::actingAs($teammate)->test(ProjectShow::class, ['project' => $project])->call('deleteProject')->assertForbidden();
    }

    public function test_creating_a_project_with_the_builder(): void
    {
        $manager = $this->person(UserRole::Manager);
        $member = $this->person();

        Livewire::actingAs($manager)->test(ProjectForm::class)
            ->set('name', 'Office move')
            ->set('dueDate', today()->addMonth()->toDateString())
            ->call('toggleMember', $member->id)
            ->set('taskRows.0.title', 'Book movers')
            ->set('taskRows.0.subtasks', "Get quotes\nSign contract")
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $project = Project::where('name', 'Office move')->firstOrFail();
        $this->assertTrue($project->isMember($member));
        $this->assertSame(3, $project->tasks()->count());
    }

    public function test_creating_tasks_from_the_form_and_quick_create(): void
    {
        $member = $this->person();

        Livewire::actingAs($member)->test(TaskForm::class)
            ->set('title', 'From the form')
            ->set('status', 'in_progress')
            ->set('progress', 40)
            ->call('setDue', 3)
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::where('title', 'From the form')->firstOrFail();
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertSame(today()->addDays(3)->toDateString(), $task->due_date->toDateString());

        Livewire::actingAs($member)->test(QuickCreate::class)
            ->call('openModal')
            ->set('title', 'Quick one')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('tasks', ['title' => 'Quick one', 'assignee_id' => $member->id]);
    }

    public function test_suggestions_on_project_cards(): void
    {
        $owner = $this->person();
        $member = $this->person();
        $project = app(ProjectService::class)->create(['name' => 'Ideas', 'members' => [['user_id' => $member->id]]], $owner);

        Livewire::actingAs($member)->test(ProjectIndex::class)
            ->call('openSuggestion', $project->id)
            ->set('suggestion', 'Add a FAQ page')
            ->call('saveSuggestion')
            ->assertHasNoErrors();

        $this->assertSame(1, $project->suggestions()->count());
        $this->assertSame(1, $owner->notifications()->where('type', 'suggestion_added')->count());
    }

    public function test_dashboard_review_and_focus_actions(): void
    {
        $manager = $this->person(UserRole::Manager);
        $member = $this->person();
        $task = app(TaskService::class)->create(['title' => 'Today', 'assignee_id' => $member->id, 'due_date' => today()->toDateString()], $manager);
        app(TaskService::class)->applyUpdate($task, $member, null, 20, 'Started');

        Livewire::actingAs($manager)->test(Dashboard::class)
            ->assertSee('Started')
            ->call('reviewRemark', $task->remarks()->first()->id);
        $this->assertTrue($task->remarks()->first()->isReviewed());

        Livewire::actingAs($member)->test(Dashboard::class)
            ->assertSee('Today')
            ->call('dismissFocus', $task->id);
        $this->assertTrue(app(\App\Services\CollaborationService::class)->focusFor($member)->isEmpty());
    }

    public function test_admin_user_management_and_settings(): void
    {
        $admin = $this->person(UserRole::Admin);
        $member = $this->person();

        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'New Person')
            ->set('username', 'new.person')
            ->set('email', 'new@example.com')
            ->set('role', 'intern')
            ->set('password', 'Secret-123')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(UserRole::Intern, User::where('username', 'new.person')->first()->role);

        Livewire::actingAs($admin)->test(UserIndex::class)->call('deactivate', $member->id);
        $this->assertFalse($member->refresh()->is_active);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('values.deadline__due_soon_days', 5)
            ->set('values.general__app_name', 'ICM Tasks')
            ->call('save')
            ->assertHasNoErrors();
        Settings::flush();
        $this->assertSame(5, Settings::int('deadline.due_soon_days'));
        $this->assertSame('ICM Tasks', Settings::string('general.app_name'));

        Livewire::actingAs($member->refresh())->test(SettingsPage::class)->assertForbidden();
    }

    public function test_activity_logs_list_notifications_in_a_table(): void
    {
        $admin = $this->person(UserRole::Admin);
        $admin->notify(new AppNotification(NotificationType::TaskAssigned, 'You were assigned "Write report"', 'Due Friday'));

        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder(['Activity Logs', 'Audit trail'])
            ->assertDontSee('> Notifications', false)
            ->assertSee('<table class="table">', false)
            ->assertSeeInOrder(['When', 'Type', 'Activity', 'Details', 'Status'])
            ->assertSee('You were assigned &quot;Write report&quot;', false)
            ->assertSee('Due Friday')
            ->assertSee('Unread')
            ->assertSee('x-data="confirmDialog"', false);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->assertSame('Department Supervisor', UserRole::Manager->label());
    }
}
