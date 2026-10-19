<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\DailyReport;
use App\Models\Remark;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\DailyReport as Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The daily report: each day's updates grouped by project, marked reported in the daily meeting.
 */
class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $member;

    private Task $projectTask;

    private Task $standaloneTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->person(UserRole::Manager);
        $this->member = $this->person();

        $project = app(ProjectService::class)->create([
            'name' => 'Network Upgrade',
            'owner_id' => $this->manager->id,
            'department_id' => $this->manager->department_id,
            'members' => [['user_id' => $this->member->id]],
        ], $this->manager);

        $this->projectTask = app(TaskService::class)->create(['project_id' => $project->id, 'title' => 'Replace core switch', 'assignee_id' => $this->member->id], $this->manager);
        $this->standaloneTask = app(TaskService::class)->create(['title' => 'Renew SSL certificates', 'assignee_id' => $this->member->id], $this->manager);
    }

    private function update(Task $task, User $by, string $text, ?int $progress = null): Remark
    {
        app(TaskService::class)->applyUpdate($task->refresh(), $by, null, $progress, $text);

        return Remark::query()->latest('id')->firstOrFail();
    }

    public function test_the_report_lists_the_days_updates_grouped_by_project(): void
    {
        $this->travelTo(today()->subDay()->setTime(15, 0));
        $this->update($this->projectTask, $this->member, 'Yesterday: ordered the switch');
        $this->travelBack();

        $this->update($this->projectTask, $this->member, 'Switch installed in rack A', 60);
        $this->update($this->standaloneTask, $this->member, 'Certificates renewed');

        $this->actingAs($this->manager)->get(route('reports.daily'))->assertOk();

        // The component's own HTML: the layout's notification bell also quotes recent updates.
        Livewire::actingAs($this->manager)->test(DailyReport::class)
            ->assertSeeInOrder(['Network Upgrade', 'Switch installed in rack A', Report::STANDALONE, 'Certificates renewed'])
            ->assertSee('0% → 60%')
            ->assertDontSee('Yesterday: ordered the switch')
            ->set('date', today()->subDay()->toDateString())
            ->assertSee('Yesterday: ordered the switch')
            ->assertDontSee('Switch installed in rack A');
    }

    public function test_updates_on_tasks_you_cannot_see_are_left_out(): void
    {
        $hrManager = $this->person(UserRole::Manager, $this->department('HR'));
        $hrMember = $this->person(UserRole::Member, $this->department('HR'));
        $hrTask = app(TaskService::class)->create(['title' => 'Payroll calendar', 'assignee_id' => $hrMember->id], $hrManager);
        $this->update($hrTask, $hrMember, 'HR-only progress note');
        $this->update($this->projectTask, $this->member, 'IT progress note');

        Livewire::actingAs($this->member)->test(DailyReport::class)
            ->assertSee('IT progress note')->assertDontSee('HR-only progress note');

        Livewire::actingAs($this->person(UserRole::Admin))->test(DailyReport::class)
            ->assertSee('IT progress note')->assertSee('HR-only progress note');
    }

    public function test_supervisors_mark_updates_reported_and_others_cannot(): void
    {
        $remark = $this->update($this->projectTask, $this->member, 'Cabling done');

        Livewire::actingAs($this->member)->test(DailyReport::class)
            ->assertSee('Not reported')
            ->assertDontSee('Mark reported')
            ->call('markReported', $remark->id)
            ->assertForbidden();
        $this->assertFalse($remark->refresh()->isReviewed());

        Livewire::actingAs($this->manager)->test(DailyReport::class)
            ->call('markReported', $remark->id)
            ->assertHasNoErrors();

        $remark->refresh();
        $this->assertTrue($remark->isReviewed());
        $this->assertSame($this->manager->id, $remark->reviewed_by);
        $this->assertSame(1, $this->member->notifications()->where('type', 'remark_reviewed')->count());
    }

    public function test_mark_all_reported_only_marks_what_you_may_report(): void
    {
        $theirs = $this->update($this->projectTask, $this->member, 'Member update');
        $other = $this->update($this->standaloneTask, $this->member, 'Another member update');
        $own = $this->update($this->projectTask, $this->manager, 'Manager\'s own update');

        Livewire::actingAs($this->manager)->test(DailyReport::class)->call('markAllReported', null);

        $this->assertTrue($theirs->refresh()->isReviewed());
        $this->assertTrue($other->refresh()->isReviewed());
        $this->assertFalse($own->refresh()->isReviewed(), 'Nobody reports their own update.');
    }

    public function test_mark_all_reported_can_target_one_project(): void
    {
        $inProject = $this->update($this->projectTask, $this->member, 'Project update');
        $standalone = $this->update($this->standaloneTask, $this->member, 'Standalone update');

        Livewire::actingAs($this->manager)->test(DailyReport::class)->call('markAllReported', 'Network Upgrade');

        $this->assertTrue($inProject->refresh()->isReviewed());
        $this->assertFalse($standalone->refresh()->isReviewed());
    }

    public function test_pending_filter_and_backlog_of_earlier_days(): void
    {
        $this->travelTo(today()->subDays(3)->setTime(10, 0));
        $this->update($this->projectTask, $this->member, 'Old unreported update');
        $this->travelBack();

        $reported = $this->update($this->projectTask, $this->member, 'Already covered');
        app(\App\Services\CollaborationService::class)->review($reported, $this->manager);
        $this->update($this->standaloneTask, $this->member, 'Still to cover');

        Livewire::actingAs($this->manager)->test(DailyReport::class)
            ->set('state', 'pending')
            ->assertSee('Still to cover')
            ->assertDontSee('Already covered')
            ->assertSee('1 update from earlier days is still not reported.')
            ->call('previousDay')
            ->assertSet('date', today()->subDay()->toDateString());
    }

    public function test_csv_export_matches_what_the_person_can_see(): void
    {
        $remark = $this->update($this->projectTask, $this->member, 'Exported update', 50);
        app(\App\Services\CollaborationService::class)->review($remark, $this->manager);

        $csv = $this->actingAs($this->manager)->get(route('export.daily'))->assertOk()->streamedContent();

        $this->assertStringContainsString('"Network Upgrade","Replace core switch"', $csv);
        $this->assertStringContainsString('Exported update', $csv);
        $this->assertStringContainsString('0% → 50%', $csv);
        $this->assertStringContainsString(',Yes,"'.$this->manager->name.'",', $csv);
    }

    public function test_meeting_mode_shows_the_latest_updates(): void
    {
        $this->update($this->projectTask, $this->member, 'Shown in the meeting');

        $this->actingAs($this->person(UserRole::Admin))->get(route('meeting'))
            ->assertOk()
            ->assertSee('Latest updates')
            ->assertSee('Shown in the meeting')
            ->assertSee('1 not reported yet');
    }

    public function test_dates_are_never_in_the_future(): void
    {
        $this->assertTrue(Report::day(today()->addDay()->toDateString())->isToday());
        $this->assertTrue(Report::day('not-a-date')->isToday());
        $this->assertTrue(Report::day(null)->isToday());
        $this->assertSame('2026-01-05', Report::day('2026-01-05')->toDateString());
    }
}
