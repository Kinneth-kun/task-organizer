<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\ActiveTaskSession;
use App\Models\Task;
use App\Services\CollaborationService;
use App\Services\DeadlineService;
use App\Services\DigestService;
use App\Services\TaskService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_deadline_check_reminds_once_and_leaves_overdue_tasks_alone(): void
    {
        $owner = $this->person();
        $tasks = app(TaskService::class);
        $late = $tasks->create(['title' => 'Late', 'due_date' => today()->subDays(2)->toDateString()], $owner);
        $soon = $tasks->create(['title' => 'Soon', 'due_date' => today()->addDay()->toDateString()], $owner);

        $first = app(DeadlineService::class)->run();
        $second = app(DeadlineService::class)->run();

        $this->assertSame(TaskStatus::Pending, $late->refresh()->status, 'overdue tasks keep their status');
        $this->assertSame(1, $first['reminders']);
        $this->assertSame(0, $second['reminders'], 'one reminder per due date');
        $this->assertDatabaseHas('notifications', ['type' => 'deadline_approaching', 'notifiable_id' => $owner->id]);

        // Moving the due date allows a new reminder.
        $tasks->updateDetails($soon, ['due_date' => today()->addDays(2)->toDateString()], $owner);
        $this->assertSame(1, app(DeadlineService::class)->run()['reminders']);
    }

    public function test_the_scheduled_command_runs(): void
    {
        $this->artisan('tasks:check-deadlines')->assertSuccessful();
        $this->artisan('active-tasks:expire')->assertSuccessful();
        $this->artisan('digest:send', ['--dry-run' => true])->assertSuccessful();
    }

    public function test_the_daily_digest(): void
    {
        $busy = $this->person();
        $idle = $this->person();
        $tasks = app(TaskService::class);
        $tasks->create(['title' => 'Due today', 'due_date' => today()->toDateString()], $busy);
        $tasks->create(['title' => 'Next week', 'due_date' => today()->addDays(10)->toDateString()], $busy);

        $dry = app(DigestService::class)->sendTo($busy, dryRun: true);
        $this->assertSame('dry run', $dry['status']);
        $this->assertStringContainsString('1 due today', $dry['message']);
        $this->assertDatabaseMissing('notifications', ['type' => 'daily_digest']);

        $results = collect(app(DigestService::class)->send())->keyBy('user');
        $this->assertSame('sent', $results[$busy->username]['status']);
        $this->assertSame('skipped (nothing open)', $results[$idle->username]['status']);
        $this->assertSame(1, $busy->notifications()->where('type', 'daily_digest')->count());

        $again = collect(app(DigestService::class)->send())->keyBy('user');
        $this->assertSame('already sent today', $again[$busy->username]['status']);

        Settings::set(['digest.enabled' => false]);
        $this->assertSame('disabled', app(DigestService::class)->send()[0]['status']);
    }

    public function test_mentions_only_notify_people_who_can_open_the_task(): void
    {
        $it = $this->department('IT');
        $hr = $this->department('HR');
        $author = $this->person(UserRole::Member, $it);
        $teammate = $this->person(UserRole::Manager, $it, ['username' => 'ana.it']);
        $stranger = $this->person(UserRole::Member, $hr, ['username' => 'ben.hr']);

        $task = app(TaskService::class)->create(['title' => 'Secret'], $author);
        app(CollaborationService::class)->comment($task, 'Thoughts @ana.it and @ben.hr? Also @nobody', [], $author);

        $this->assertSame(1, $teammate->notifications()->where('type', 'mentioned')->count());
        $this->assertSame(0, $stranger->notifications()->count());
        $this->assertSame(0, $author->notifications()->count(), 'nobody is notified about their own action');
    }

    public function test_updates_notify_stakeholders_but_not_the_actor(): void
    {
        $owner = $this->person();
        $assignee = $this->person();
        $task = app(TaskService::class)->create(['title' => 'Shared', 'assignee_id' => $assignee->id], $owner);
        $this->assertSame(1, $assignee->notifications()->where('type', 'task_assigned')->count());

        app(TaskService::class)->applyUpdate($task, $assignee, 'completed', 100, 'Done!');

        $this->assertSame(1, $owner->notifications()->where('type', 'task_completed')->count());
        $this->assertSame(0, $assignee->notifications()->where('type', 'task_completed')->count());
    }

    public function test_email_goes_only_to_people_who_opted_in(): void
    {
        Notification::fake();
        $owner = $this->person();
        $optedIn = $this->person(attributes: ['email_notifications' => ['task_assigned']]);
        $optedOut = $this->person();

        $tasks = app(TaskService::class);
        $tasks->create(['title' => 'A', 'assignee_id' => $optedIn->id], $owner);
        $tasks->create(['title' => 'B', 'assignee_id' => $optedOut->id], $owner);

        Notification::assertSentTo($optedIn, \App\Notifications\AppNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));
        Notification::assertSentTo($optedOut, \App\Notifications\AppNotification::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_working_sessions(): void
    {
        $user = $this->person();
        $collaboration = app(CollaborationService::class);
        $first = app(TaskService::class)->create(['title' => 'First'], $user);
        $second = app(TaskService::class)->create(['title' => 'Second'], $user);

        $collaboration->startWorking($first, $user);
        $collaboration->startWorking($second, $user);
        $this->assertSame([$second->id], ActiveTaskSession::pluck('task_id')->all(), 'one task at a time');

        // Completing the task ends the session.
        app(TaskService::class)->applyUpdate($second, $user, 'completed');
        $this->assertSame(0, ActiveTaskSession::count());

        $collaboration->startWorking($first, $user);
        $this->travel(9)->hours();
        $this->assertSame(0, ActiveTaskSession::query()->live()->count());
        $this->assertSame(1, $collaboration->expireSessions());
    }

    public function test_focus_of_the_day(): void
    {
        $user = $this->person();
        $collaboration = app(CollaborationService::class);
        $tasks = app(TaskService::class);
        $today = $tasks->create(['title' => 'Due today', 'due_date' => today()->toDateString()], $user);
        $pinned = $tasks->create(['title' => 'Pinned', 'due_date' => today()->addMonth()->toDateString()], $user);
        $tasks->create(['title' => 'Later', 'due_date' => today()->addMonth()->toDateString()], $user);

        $collaboration->toggleFocus($pinned, $user);
        $this->assertSame(['Pinned', 'Due today'], $collaboration->focusFor($user)->pluck('title')->all());

        $collaboration->dismissForToday($today, $user);
        $this->assertSame(['Pinned'], $collaboration->focusFor($user)->pluck('title')->all());

        $this->travel(1)->days();
        $this->assertContains('Due today', $collaboration->focusFor($user)->pluck('title')->all(), 'dismissals last one day');
    }
}
