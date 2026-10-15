<?php

namespace Tests\Feature;

use App\Enums\ProjectHealth;
use App\Models\Project;
use App\Services\ProjectHealthService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $tasks = [], array $attributes = []): Project
    {
        $owner = $this->person();
        $project = app(ProjectService::class)->create(['name' => 'P', ...$attributes], $owner);
        foreach ($tasks as $task) {
            $created = app(TaskService::class)->create(['project_id' => $project->id, 'title' => 'T', 'due_date' => $task['due'] ?? null], $owner);
            if (isset($task['progress'])) {
                app(TaskService::class)->applyUpdate($created, $owner, $task['status'] ?? null, $task['progress']);
            }
        }

        return $project->refresh();
    }

    private function health(Project $project): ProjectHealth
    {
        return app(ProjectHealthService::class)->evaluate($project->refresh())['health'];
    }

    public function test_a_healthy_project_is_on_track(): void
    {
        $this->assertSame(ProjectHealth::OnTrack, $this->health($this->project([['progress' => 50]])));
    }

    public function test_status_overrides(): void
    {
        $project = $this->project([['progress' => 10]]);
        $project->update(['status' => 'on_hold']);
        $this->assertSame(ProjectHealth::OnHold, $this->health($project));

        $project->update(['status' => 'completed']);
        $this->assertSame(ProjectHealth::Completed, $this->health($project));
    }

    public function test_all_tasks_done_is_completed(): void
    {
        $this->assertSame(ProjectHealth::Completed, $this->health($this->project([['progress' => 100], ['progress' => 100]])));
    }

    public function test_a_project_past_its_due_date_is_delayed(): void
    {
        $project = $this->project([['progress' => 50]], ['due_date' => today()->subDay()->toDateString()]);
        $this->assertSame(ProjectHealth::Delayed, $this->health($project));

        Settings::set(['health.overdue_project_is_delayed' => false]);
        $this->assertNotSame(ProjectHealth::Delayed, $this->health($project));
    }

    public function test_delayed_share_of_open_tasks(): void
    {
        $late = ['due' => today()->subDay()->toDateString()];

        // 1 of 4 open delayed = 25% ≥ 25% → Delayed.
        $this->assertSame(ProjectHealth::Delayed, $this->health($this->project([$late, [], [], []])));
        // 1 of 5 = 20%: below Delayed, above At Risk (1%).
        $this->assertSame(ProjectHealth::AtRisk, $this->health($this->project([$late, [], [], [], []])));
    }

    public function test_behind_schedule_is_at_risk(): void
    {
        $project = $this->project([['progress' => 10]], [
            'start_date' => today()->subDays(50)->toDateString(),
            'due_date' => today()->addDays(50)->toDateString(),
        ]);

        $evaluation = app(ProjectHealthService::class)->evaluate($project);
        $this->assertSame(ProjectHealth::AtRisk, $evaluation['health']);
        $this->assertSame(50, $evaluation['stats']['expected_progress']);
    }

    public function test_due_soon_with_low_progress_is_at_risk(): void
    {
        $project = $this->project([['progress' => 40]], ['due_date' => today()->addDays(2)->toDateString()]);
        $this->assertSame(ProjectHealth::AtRisk, $this->health($project));
    }

    public function test_health_is_cached_on_the_project(): void
    {
        $project = $this->project([['due' => today()->subDay()->toDateString()]]);
        $this->assertSame(ProjectHealth::Delayed, $project->health);
    }
}
