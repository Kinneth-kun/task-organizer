<?php

namespace Database\Seeders;

use App\Enums\ProjectMemberRole;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use App\Models\User;
use App\Services\CollaborationService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample people, projects, subtasks and history for trying the app. Every demo account's
 * password is "password". Built through the services so history, roll-ups and health are real.
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(ProjectService $projects, TaskService $tasks, CollaborationService $collaboration): void
    {
        $this->call(DatabaseSeeder::class);

        if (User::query()->where('username', 'jamie.reyes')->exists()) {
            $this->command?->warn('Demo data already exists — skipped.');

            return;
        }

        $dept = Department::query()->pluck('id', 'code');

        $person = fn (string $name, string $username, UserRole $role, string $code, string $title, string $color) => User::create([
            'name' => $name,
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => $role,
            'department_id' => $dept[$code],
            'job_title' => $title,
            'avatar_color' => $color,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $ceo = $person('Victoria Santos', 'victoria.santos', UserRole::Executive, 'EXEC', 'Chief Executive Officer', 'violet');
        $itLead = $person('Jamie Reyes', 'jamie.reyes', UserRole::Manager, 'IT', 'IT Manager', 'sky');
        $mktLead = $person('Andrea Cruz', 'andrea.cruz', UserRole::Manager, 'MKT', 'Marketing Manager', 'fuchsia');
        $dev = $person('Miguel Tan', 'miguel.tan', UserRole::Member, 'IT', 'Software Developer', 'indigo');
        $ops = $person('Paolo Garcia', 'paolo.garcia', UserRole::Member, 'IT', 'Systems Analyst', 'teal');
        $designer = $person('Bea Mendoza', 'bea.mendoza', UserRole::Member, 'MKT', 'Graphic Designer', 'amber');
        $intern = $person('Carlo Lim', 'carlo.lim', UserRole::Intern, 'IT', 'IT Intern', 'emerald');
        $hr = $person('Rina Flores', 'rina.flores', UserRole::Member, 'HR', 'HR Officer', 'rose');

        // ---- An IT project in full swing, with nested subtasks.
        $portal = $projects->create([
            'name' => 'Employee Self-Service Portal',
            'description' => 'Leave requests, payslips and profile updates in one place for every employee.',
            'owner_id' => $itLead->id,
            'department_id' => $dept['IT'],
            'priority' => 'high',
            'color' => 'sky',
            'start_date' => today()->subDays(30)->toDateString(),
            'due_date' => today()->addDays(25)->toDateString(),
            'members' => [
                ['user_id' => $dev->id, 'role' => ProjectMemberRole::Manager->value],
                ['user_id' => $ops->id],
                ['user_id' => $intern->id],
            ],
        ], $itLead);

        $requirements = $tasks->create(['project_id' => $portal->id, 'title' => 'Gather requirements', 'assignee_id' => $ops->id, 'due_date' => today()->subDays(18)->toDateString(), 'priority' => 'high'], $itLead);
        $tasks->applyUpdate($requirements, $ops, 'completed', 100, 'Signed off by HR and Accounting.');

        $build = $tasks->create(['project_id' => $portal->id, 'title' => 'Build leave module', 'assignee_id' => $dev->id, 'due_date' => today()->addDays(10)->toDateString(), 'priority' => 'high'], $itLead);
        $api = $tasks->create(['parent_id' => $build->id, 'title' => 'Leave request API', 'assignee_id' => $dev->id, 'due_date' => today()->addDays(4)->toDateString()], $itLead);
        $tasks->create(['parent_id' => $api->id, 'title' => 'Approval workflow endpoints', 'assignee_id' => $dev->id], $dev);
        $validation = $tasks->create(['parent_id' => $api->id, 'title' => 'Leave balance validation', 'assignee_id' => $intern->id], $dev);
        $tasks->applyUpdate($validation, $intern, null, 60, 'Balances now checked against the HRIS export. @miguel.tan please review.');
        $ui = $tasks->create(['parent_id' => $build->id, 'title' => 'Leave request screens', 'assignee_id' => $ops->id, 'due_date' => today()->addDays(8)->toDateString()], $itLead);
        $tasks->applyUpdate($ui, $ops, null, 35, 'List and form done; calendar view next.');

        $payslips = $tasks->create(['project_id' => $portal->id, 'title' => 'Payslip viewer', 'assignee_id' => $ops->id, 'due_date' => today()->subDays(2)->toDateString()], $itLead);
        $tasks->applyUpdate($payslips, $ops, null, 40, 'Waiting for the payroll PDF template.');
        $tasks->create(['project_id' => $portal->id, 'title' => 'User acceptance testing', 'assignee_id' => $intern->id, 'due_date' => today()->addDays(20)->toDateString()], $itLead);

        $collaboration->comment($build, 'Kick-off notes are in the shared drive. @paolo.garcia can you take the screens?', [], $itLead);
        $collaboration->startWorking($api, $dev);
        $collaboration->startWorking($validation, $intern);

        // ---- A marketing project that's behind schedule.
        $campaign = $projects->create([
            'name' => 'Q4 Leasing Campaign',
            'description' => 'Print, social and on-site materials for the year-end leasing push.',
            'owner_id' => $mktLead->id,
            'department_id' => $dept['MKT'],
            'priority' => 'urgent',
            'color' => 'fuchsia',
            'start_date' => today()->subDays(20)->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
            'members' => [['user_id' => $designer->id]],
            'tasks' => [
                ['title' => 'Campaign brief', 'assignee_id' => $mktLead->id, 'due_date' => today()->subDays(14)->toDateString()],
                ['title' => 'Social media kit', 'assignee_id' => $designer->id, 'due_date' => today()->subDays(1)->toDateString(), 'subtasks' => ['Instagram carousel', 'Facebook cover']],
                ['title' => 'Lobby standees', 'assignee_id' => $designer->id, 'due_date' => today()->addDays(3)->toDateString()],
            ],
        ], $mktLead);
        $brief = $campaign->tasks()->where('title', 'Campaign brief')->first();
        $tasks->applyUpdate($brief, $mktLead, 'completed', 100, 'Approved by Victoria.');

        // ---- A completed project with post-launch work.
        $intranet = $projects->create([
            'name' => 'Company Intranet Refresh',
            'description' => 'New look and navigation for the intranet.',
            'owner_id' => $itLead->id,
            'department_id' => $dept['IT'],
            'color' => 'emerald',
            'start_date' => today()->subDays(90)->toDateString(),
            'due_date' => today()->subDays(15)->toDateString(),
            'members' => [['user_id' => $dev->id]],
            'tasks' => [['title' => 'New homepage'], ['title' => 'Navigation menu'], ['title' => 'Content migration']],
        ], $itLead);
        foreach ($intranet->tasks as $task) {
            $tasks->applyUpdate($task, $dev, 'completed', 100);
        }
        $projects->complete($intranet->refresh(), $itLead);
        $tasks->create(['project_id' => $intranet->id, 'title' => 'Fix broken links on HR page', 'category' => 'bug_fix', 'assignee_id' => $dev->id, 'due_date' => today()->addDays(2)->toDateString()], $itLead);
        $tasks->create(['project_id' => $intranet->id, 'title' => 'Add dark mode', 'category' => 'enhancement', 'assignee_id' => $dev->id], $itLead);

        // ---- Standalone work.
        $tasks->create(['title' => 'Renew SSL certificates', 'assignee_id' => $ops->id, 'due_date' => today()->toDateString(), 'priority' => 'urgent'], $itLead);
        $tasks->create(['title' => 'Prepare onboarding kit for new hires', 'assignee_id' => $hr->id, 'due_date' => today()->addDays(6)->toDateString()], $hr);
        $tasks->create(['title' => 'Learn the deployment checklist', 'due_date' => today()->addDays(3)->toDateString()], $intern);

        // A supervisor already reviewed one remark.
        $remark = Remark::query()->where('task_id', $requirements->id)->first();
        if ($remark) {
            $collaboration->review($remark, $itLead);
        }

        $collaboration->toggleFocus($build, $dev);

        // Spread history over the past weeks so timelines look lived-in.
        DB::table('projects')->whereIn('id', [$portal->id, $campaign->id, $intranet->id])->update(['created_at' => today()->subDays(30)]);
        Task::query()->where('project_id', $intranet->id)->whereNull('category')->update(['created_at' => today()->subDays(80)]);

        $this->command?->info('Demo data created. Demo accounts use the password "password" — e.g. jamie.reyes (manager), miguel.tan (member), carlo.lim (intern), victoria.santos (executive).');
    }
}
