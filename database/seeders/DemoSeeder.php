<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Project;
use App\Models\Remark;
use App\Models\Task;
use App\Models\User;
use App\Services\CollaborationService;
use App\Services\DeadlineService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sample people, projects and tasks for demonstrations:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * The last three months are replayed through ProjectService, TaskService and CollaborationService
 * with the clock moved back to each event, so history, remarks, roll-ups, project health,
 * notifications and the audit log all look like real use. Dates are relative to today.
 *
 * Every demo account signs in with DEMO_PASSWORD from .env (default below). Runs once: it stops
 * if the demo people already exist.
 */
class DemoSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    /** username => [name, role, department code, job title, avatar colour] */
    private const PEOPLE = [
        'ricardo.santos' => ['Ricardo Santos', UserRole::Executive, 'EXEC', 'General Manager', 'violet'],
        'maria.reyes' => ['Maria Reyes', UserRole::Manager, 'IT', 'IT Manager', 'sky'],
        'john.cruz' => ['John Cruz', UserRole::Member, 'IT', 'Web Developer', 'indigo'],
        'angela.lim' => ['Angela Lim', UserRole::Member, 'IT', 'Systems Analyst', 'teal'],
        'paolo.garcia' => ['Paolo Garcia', UserRole::Intern, 'IT', 'IT Intern', 'cyan'],
        'carla.mendoza' => ['Carla Mendoza', UserRole::Manager, 'MKT', 'Marketing Manager', 'fuchsia'],
        'kevin.tan' => ['Kevin Tan', UserRole::Member, 'MKT', 'Graphic Designer', 'orange'],
        'bea.villanueva' => ['Bea Villanueva', UserRole::Member, 'MKT', 'Social Media Specialist', 'rose'],
        'daniel.ramos' => ['Daniel Ramos', UserRole::Manager, 'LSG', 'Leasing Manager', 'amber'],
        'sofia.aquino' => ['Sofia Aquino', UserRole::Member, 'LSG', 'Leasing Officer', 'emerald'],
        'mark.bautista' => ['Mark Bautista', UserRole::Manager, 'SEC', 'Head of Security', 'indigo'],
        'joel.navarro' => ['Joel Navarro', UserRole::Member, 'SEC', 'Security Supervisor', 'sky'],
        'grace.torres' => ['Grace Torres', UserRole::Manager, 'HR', 'HR Manager', 'rose'],
        'liza.fernandez' => ['Liza Fernandez', UserRole::Member, 'HR', 'HR Associate', 'violet'],
        'nina.castro' => ['Nina Castro', UserRole::Intern, 'HR', 'HR Intern', 'amber'],
        'roberto.dizon' => ['Roberto Dizon', UserRole::Manager, 'ACC', 'Finance Manager', 'emerald'],
        'ella.morales' => ['Ella Morales', UserRole::Member, 'ACC', 'Accountant', 'teal'],
        'victor.aguilar' => ['Victor Aguilar', UserRole::Manager, 'OPS', 'Operations Manager', 'orange'],
        'tess.ocampo' => ['Tess Ocampo', UserRole::Member, 'OPS', 'Facilities Coordinator', 'cyan'],
    ];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, int> task title => id */
    private array $taskIds = [];

    /** @var array<string, int> project name => id */
    private array $projectIds = [];

    /** @var array<string, int> "task title#n" => remark id, for reviews */
    private array $remarkIds = [];

    /** @var list<array{int, int, Closure}> [days ago, order, action] */
    private array $events = [];

    private Carbon $today;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
        private readonly CollaborationService $collaboration,
        private readonly DeadlineService $deadlines,
    ) {}

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        if (User::query()->whereIn('username', array_keys(self::PEOPLE))->exists()) {
            $this->command?->warn('Demo data is already loaded — skipped. Run "php artisan migrate:fresh --seed" first to start over.');

            return;
        }

        $this->today = today();

        DB::transaction(function () {
            try {
                $this->createPeople();
                $this->scheduleProjects();
                $this->scheduleStandaloneTasks();
                $this->replay();
            } finally {
                Carbon::setTestNow();
            }

            $this->finishToday();
        });

        $this->command?->info(sprintf(
            'Demo data loaded: %d people, %d projects, %d tasks. Sign in as any of them (e.g. "maria.reyes") with DEMO_PASSWORD.',
            count($this->users), count($this->projectIds), count($this->taskIds),
        ));
    }

    /* ================================================================== People */

    private function createPeople(): void
    {
        $departments = Department::query()->pluck('id', 'code');
        $password = env('DEMO_PASSWORD') ?: self::DEFAULT_PASSWORD;

        foreach (self::PEOPLE as $username => [$name, $role, $department, $title, $color]) {
            $this->users[$username] = User::query()->create([
                'name' => $name,
                'username' => $username,
                'email' => "{$username}@example.com",
                'role' => $role,
                'department_id' => $departments[$department] ?? null,
                'job_title' => $title,
                'avatar_color' => $color,
                'is_active' => true,
                'password' => $password,
            ]);
            $this->users[$username]->forceFill(['email_verified_at' => now()->subDays(100), 'created_at' => now()->subDays(100)])->save();
        }
    }

    /* ================================================================== Projects */

    private function scheduleProjects(): void
    {
        $this->project([
            'name' => 'Mall Website Redesign',
            'description' => 'A faster, mobile-friendly website with a searchable store directory, events page and tenant inquiries.',
            'owner' => 'maria.reyes', 'priority' => 'high', 'color' => 'sky', 'start' => -45, 'due' => 30, 'at' => 46,
            'managers' => ['carla.mendoza'],
            'tasks' => [
                ['title' => 'Gather requirements from department heads', 'assignee' => 'angela.lim', 'start' => -45, 'due' => -35, 'at' => 45,
                    'log' => [[44, 30, 'Met with Marketing and Leasing.'], [40, 70, 'HR and Security inputs collected.'], [37, 100, 'Requirements document signed off by all department heads.', true]]],
                ['title' => 'Design new homepage and store directory', 'assignee' => 'kevin.tan', 'collaborators' => ['bea.villanueva'], 'priority' => 'high', 'start' => -35, 'due' => -5, 'at' => 40,
                    'subtasks' => [
                        ['title' => 'Wireframes for homepage', 'assignee' => 'kevin.tan', 'due' => -25,
                            'log' => [[33, 50], [28, 100, 'Wireframes approved by @carla.mendoza.']]],
                        ['title' => 'High-fidelity mockups', 'assignee' => 'kevin.tan', 'due' => -8,
                            'log' => [[24, 40], [15, 80, 'Desktop done; mobile layouts in progress.'], [9, 100, 'Final mockups uploaded to the shared drive.', true]]],
                        ['title' => 'Brand style guide for the web', 'assignee' => 'bea.villanueva', 'due' => -5,
                            'log' => [[20, 60], [6, 100]]],
                    ]],
                ['title' => 'Build store directory with search and filters', 'assignee' => 'john.cruz', 'collaborators' => ['angela.lim'], 'priority' => 'high', 'start' => -20, 'due' => 10, 'at' => 30,
                    'comments' => [
                        [12, 'carla.mendoza', 'Can we highlight new tenants at the top of the directory?'],
                        [11, 'john.cruz', 'Yes — I will add a "New" badge and a sort option. @kevin.tan can you share the badge design?'],
                        [10, 'kevin.tan', 'Uploaded to the shared drive under /web/badges.'],
                    ],
                    'subtasks' => [
                        ['title' => 'Tenant database API', 'assignee' => 'john.cruz', 'due' => -2,
                            'log' => [[18, 40, 'Endpoints for tenants and categories done.'], [10, 100, 'API deployed to staging.', true]]],
                        ['title' => 'Search and category filters', 'assignee' => 'john.cruz', 'due' => 6,
                            'log' => [[8, 30], [3, 65, 'Search works; filter by floor still pending.']]],
                        ['title' => 'Interactive floor map', 'assignee' => 'paolo.garcia', 'due' => 10,
                            'log' => [[5, 20, 'Exported the floor plans to SVG.']]],
                    ]],
                ['title' => 'Content migration from the old site', 'assignee' => 'paolo.garcia', 'start' => -15, 'due' => -3, 'at' => 15,
                    'log' => [[12, 20, 'Started copying tenant pages.'], [6, 45, 'About half of the store pages are migrated.']]],
                ['title' => 'Live chat widget', 'assignee' => 'john.cruz', 'priority' => 'low', 'due' => 20, 'at' => 30,
                    'log' => [[21, 'cancelled', 'Dropped — Marketing will answer inquiries through Messenger instead.']]],
                ['title' => 'Events and promotions page', 'assignee' => 'john.cruz', 'start' => 2, 'due' => 18, 'at' => 25],
                ['title' => 'Website performance and SEO audit', 'assignee' => 'angela.lim', 'priority' => 'low', 'start' => 15, 'due' => 25, 'at' => 20],
                ['title' => 'Tenant inquiry form on the Leasing page', 'assignee' => 'john.cruz', 'collaborators' => ['sofia.aquino'], 'start' => 10, 'due' => 22, 'at' => 13],
            ],
            'suggestions' => [
                [14, 'daniel.ramos', 'Please add an inquiry form for prospective tenants on the Leasing page.'],
                [7, 'ricardo.santos', 'Let us make sure the site works well on phones — most visitors will come from mobile.'],
            ],
        ]);

        $this->project([
            'name' => 'Q4 Tenant Leasing Drive',
            'description' => 'Fill the 14 vacant units before year end, starting with food and retail brands.',
            'owner' => 'daniel.ramos', 'priority' => 'high', 'color' => 'amber', 'start' => -40, 'due' => 20, 'at' => 41,
            'tasks' => [
                ['title' => 'Update vacant units list and floor plans', 'assignee' => 'sofia.aquino', 'due' => -30, 'at' => 40,
                    'log' => [[38, 50], [33, 100, 'All 14 vacant units documented with photos.', true]]],
                ['title' => 'Prepare leasing brochure', 'assignee' => 'kevin.tan', 'collaborators' => ['bea.villanueva'], 'due' => -10, 'at' => 35,
                    'log' => [[30, 30, 'Draft layout ready.'], [20, 60], [12, 90, 'Waiting for the final rental rates from Leasing.']],
                    'comments' => [
                        [11, 'kevin.tan', '@daniel.ramos we still need the final rental rates to finish the brochure.'],
                        [9, 'daniel.ramos', 'The rates are with Accounting for approval — expect them this week.'],
                    ]],
                ['title' => 'Contact prospective tenants', 'assignee' => 'sofia.aquino', 'priority' => 'urgent', 'due' => 14, 'at' => 30,
                    'subtasks' => [
                        ['title' => 'Food and beverage brands', 'assignee' => 'sofia.aquino', 'due' => 5,
                            'log' => [[25, 20], [14, 50, 'Met with 3 coffee chains; 2 are interested.'], [4, 70]]],
                        ['title' => 'Retail and fashion brands', 'assignee' => 'sofia.aquino', 'due' => 10,
                            'log' => [[20, 10], [8, 35]]],
                        ['title' => 'Services (banks, clinics, salons)', 'assignee' => 'sofia.aquino', 'due' => -4,
                            'log' => [[15, 20, 'Sent proposals to 5 banks.']]],
                    ]],
                ['title' => 'Approve rental rates for 2027', 'assignee' => 'roberto.dizon', 'collaborators' => ['ella.morales'], 'priority' => 'high', 'due' => -6, 'at' => 25,
                    'log' => [[22, 30, 'Market comparison started.'], [10, 60, 'Draft rates submitted for GM review.']]],
                ['title' => 'Host leasing open house', 'assignee' => 'sofia.aquino', 'collaborators' => ['tess.ocampo'], 'start' => 15, 'due' => 18, 'at' => 10],
            ],
            'suggestions' => [
                [16, 'carla.mendoza', 'We could promote the open house on our social media pages a week before.'],
            ],
        ]);

        $this->project([
            'name' => 'CCTV System Upgrade',
            'description' => 'Replace analog cameras, cover the parking blind spots and keep 60 days of footage.',
            'owner' => 'mark.bautista', 'priority' => 'urgent', 'color' => 'teal', 'start' => -60, 'due' => -5, 'at' => 62,
            'tasks' => [
                ['title' => 'Site survey of camera blind spots', 'assignee' => 'joel.navarro', 'due' => -50, 'at' => 60,
                    'log' => [[58, 50], [52, 100, 'Found 23 blind spots, mostly on parking levels B1–B2.', true]]],
                ['title' => 'Canvass and select CCTV supplier', 'assignee' => 'mark.bautista', 'collaborators' => ['ella.morales'], 'due' => -40, 'at' => 55,
                    'log' => [[50, 40, 'Received 3 quotations.'], [42, 100, 'Supplier selected and purchase order issued.']]],
                ['title' => 'Install cameras on parking levels', 'assignee' => 'joel.navarro', 'collaborators' => ['tess.ocampo'], 'priority' => 'urgent', 'due' => -10, 'at' => 40,
                    'comments' => [[8, 'mark.bautista', 'The supplier confirmed the remaining cameras arrive on Friday.']],
                    'subtasks' => [
                        ['title' => 'Level B1 installation', 'assignee' => 'joel.navarro', 'due' => -20,
                            'log' => [[30, 50], [22, 100, 'All 18 cameras on B1 are online.']]],
                        ['title' => 'Level B2 installation', 'assignee' => 'joel.navarro', 'due' => -10,
                            'log' => [[18, 30], [9, 55, 'The supplier delayed delivery of 6 cameras.']]],
                    ]],
                ['title' => 'Upgrade NVR storage to 60 days', 'assignee' => 'angela.lim', 'due' => -3, 'at' => 30,
                    'log' => [[20, 40], [10, 60]]],
                ['title' => 'Train guards on the new monitoring software', 'assignee' => 'joel.navarro', 'due' => 7, 'at' => 15],
            ],
        ]);

        $this->project([
            'name' => 'Employee Onboarding Portal',
            'description' => 'One place for new hires to complete their checklist, upload documents and sign forms.',
            'owner' => 'grace.torres', 'priority' => 'medium', 'color' => 'violet', 'start' => -95, 'due' => -30, 'at' => 96,
            'managers' => ['maria.reyes'],
            'tasks' => [
                ['title' => 'Map onboarding steps and checklists', 'assignee' => 'liza.fernandez', 'due' => -85, 'at' => 95,
                    'log' => [[92, 50], [86, 100, 'The checklist covers 32 steps across HR, IT and Security.', true]]],
                ['title' => 'Build the onboarding portal', 'assignee' => 'john.cruz', 'priority' => 'high', 'due' => -40, 'at' => 88,
                    'subtasks' => [
                        ['title' => 'New-hire checklist module', 'assignee' => 'john.cruz', 'due' => -60,
                            'log' => [[80, 40], [66, 100]]],
                        ['title' => 'Document upload and e-signature', 'assignee' => 'john.cruz', 'due' => -45,
                            'log' => [[62, 30], [50, 70], [44, 100, 'E-signature integrated and tested.']]],
                    ]],
                ['title' => 'Pilot with this month\'s new hires', 'assignee' => 'nina.castro', 'due' => -32, 'at' => 45,
                    'log' => [[40, 50, '5 new hires onboarded through the portal.'], [33, 100, 'Pilot feedback is positive; 2 minor issues logged.', true]]],
                ['title' => 'Write user guide for managers', 'assignee' => 'liza.fernandez', 'due' => -30, 'at' => 40,
                    'log' => [[36, 60], [31, 100]]],
            ],
            'status' => ['completed', 28],
            'post_launch' => [
                ['title' => 'Add reminder emails for incomplete checklists', 'category' => 'enhancement', 'assignee' => 'john.cruz', 'due' => 12, 'at' => 20,
                    'log' => [[10, 40, 'Reminder job scheduled daily at 8 AM.']]],
                ['title' => 'Fix uploads failing for files over 10 MB', 'category' => 'bug_fix', 'assignee' => 'john.cruz', 'priority' => 'high', 'due' => -2, 'at' => 12,
                    'log' => [[11, 50], [7, 100, 'Raised the upload limit and added a clear error message.', true]]],
                ['title' => 'Update handbook PDF to the 2027 version', 'category' => 'update', 'assignee' => 'liza.fernandez', 'due' => 20, 'at' => 5],
            ],
            'suggestions' => [
                [26, 'ricardo.santos', 'Great work, team. Let us roll this out to all departments next quarter.'],
            ],
        ]);

        $this->project([
            'name' => 'Holiday Season Campaign',
            'description' => 'Decor, promos and events for the holiday rush, themed "Season of Giving".',
            'owner' => 'carla.mendoza', 'priority' => 'high', 'color' => 'fuchsia', 'start' => -10, 'due' => 50, 'at' => 12,
            'tasks' => [
                ['title' => 'Campaign concept and theme', 'assignee' => 'bea.villanueva', 'collaborators' => ['kevin.tan'], 'due' => -2, 'at' => 12,
                    'log' => [[10, 50, 'Shortlisted 3 themes.'], [3, 100, '"Season of Giving" theme approved by @ricardo.santos.']]],
                ['title' => 'Holiday decor installation plan', 'assignee' => 'tess.ocampo', 'due' => 15, 'at' => 8,
                    'log' => [[4, 20, 'Sourcing quotes for the centre-court tree.']]],
                ['title' => 'Social media content calendar', 'assignee' => 'bea.villanueva', 'due' => 8, 'at' => 7,
                    'log' => [[2, 25]]],
                ['title' => 'Secure raffle permit', 'assignee' => 'victor.aguilar', 'priority' => 'urgent', 'due' => 12, 'at' => 5],
                ['title' => 'Tenant promo partnerships', 'assignee' => 'sofia.aquino', 'due' => 20, 'at' => 6],
            ],
        ]);

        $this->project([
            'name' => '2027 Budget Planning',
            'description' => 'Consolidate department budget requests into the 2027 operating budget.',
            'owner' => 'roberto.dizon', 'priority' => 'medium', 'color' => 'emerald', 'start' => -30, 'due' => 35, 'at' => 31,
            'tasks' => [
                ['title' => 'Collect department budget requests', 'assignee' => 'ella.morales', 'due' => -12, 'at' => 30,
                    'log' => [[25, 40], [15, 80, '6 of 8 departments have submitted.'], [9, 'on_hold', 'Paused until the GM confirms the 2027 revenue targets.']]],
                ['title' => 'Review utilities and maintenance costs', 'assignee' => 'ella.morales', 'due' => 10, 'at' => 20,
                    'log' => [[14, 30]]],
                ['title' => 'Draft consolidated budget', 'assignee' => 'roberto.dizon', 'due' => 30, 'at' => 20],
            ],
            'status' => ['on_hold', 9],
        ]);
    }

    /**
     * Queue a project, its team, tasks, suggestions and status change. People assigned to or
     * collaborating on its tasks become members.
     *
     * @param  array<string, mixed>  $spec
     */
    private function project(array $spec): void
    {
        $name = $spec['name'];
        $owner = $spec['owner'];
        $allTasks = [...$spec['tasks'], ...($spec['post_launch'] ?? [])];

        $members = collect($spec['managers'] ?? [])->mapWithKeys(fn ($u) => [$u => 'manager']);
        foreach ($this->people($allTasks) as $username) {
            $members[$username] ??= 'member';
        }

        $this->at($spec['at'], function () use ($spec, $name, $owner, $members) {
            $project = $this->projects->create([
                'name' => $name,
                'description' => $spec['description'],
                'owner_id' => $this->user($owner)->id,
                'priority' => $spec['priority'],
                'color' => $spec['color'],
                'start_date' => $this->date($spec['start']),
                'due_date' => $this->date($spec['due']),
                'members' => $members->map(fn ($role, $username) => ['user_id' => $this->user($username)->id, 'role' => $role])->values()->all(),
            ], $this->user($owner));
            $this->projectIds[$name] = $project->id;
        });

        foreach ($spec['tasks'] as $task) {
            $this->task($task, $name, null, $owner, $spec['at']);
        }

        foreach ($spec['suggestions'] ?? [] as [$daysAgo, $username, $body]) {
            $this->at($daysAgo, fn () => $this->projects->addSuggestion($this->projectModel($name), $body, [], $this->user($username)));
        }

        if (isset($spec['status'])) {
            [$status, $daysAgo] = $spec['status'];
            $this->at($daysAgo, fn () => $this->projects->setStatus($this->projectModel($name), ProjectStatus::from($status), $this->user($owner)));
        }

        foreach ($spec['post_launch'] ?? [] as $task) {
            $this->task($task, $name, null, $owner, $spec['status'][1] ?? $spec['at']);
        }
    }

    /* ================================================================== Standalone tasks */

    private function scheduleStandaloneTasks(): void
    {
        $standalone = [
            'maria.reyes' => [
                ['title' => 'Renew SSL certificates', 'assignee' => 'maria.reyes', 'priority' => 'high', 'due' => 3, 'at' => 6,
                    'log' => [[1, 50, 'Main site renewed; the mail server is next.']]],
                ['title' => 'Quarterly IT asset inventory', 'assignee' => 'paolo.garcia', 'due' => 14, 'at' => 4],
            ],
            'grace.torres' => [
                ['title' => 'Prepare December payroll calendar', 'assignee' => 'liza.fernandez', 'due' => 7, 'at' => 5],
            ],
            'victor.aguilar' => [
                ['title' => 'Inspect fire exits and sprinklers', 'assignee' => 'tess.ocampo', 'priority' => 'urgent', 'due' => -1, 'at' => 10,
                    'log' => [[6, 60, 'Floors 1–3 inspected; two exit signs need new bulbs.']]],
            ],
            'carla.mendoza' => [
                ['title' => 'Approve next month\'s social posts', 'assignee' => 'carla.mendoza', 'due' => 1, 'at' => 2],
            ],
            'nina.castro' => [
                ['title' => 'Organize 201 files of new hires', 'assignee' => 'nina.castro', 'due' => 5, 'at' => 8,
                    'log' => [[4, 40]]],
            ],
            'joel.navarro' => [
                ['title' => 'Monthly guard roster', 'assignee' => 'joel.navarro', 'due' => 2, 'at' => 6,
                    'log' => [[3, 100, 'Roster posted in the guard house.']]],
            ],
        ];

        foreach ($standalone as $creator => $tasks) {
            foreach ($tasks as $task) {
                $this->task($task, null, null, $creator, $task['at']);
            }
        }
    }

    /* ================================================================== Tasks */

    /**
     * Queue a task with its updates, comments and subtasks. Updates are made by the assignee;
     * a `true` fourth element in an update has the supervisor review that remark a day later.
     *
     * @param  array<string, mixed>  $spec
     */
    private function task(array $spec, ?string $project, ?string $parent, string $supervisor, int $defaultAt): void
    {
        $title = $spec['title'];
        $createdAt = $spec['at'] ?? $defaultAt;

        $this->at($createdAt, function () use ($spec, $title, $project, $parent, $supervisor) {
            $this->taskIds[$title] = $this->tasks->create([
                'project_id' => $project ? $this->projectIds[$project] : null,
                'parent_id' => $parent ? $this->taskIds[$parent] : null,
                'title' => $title,
                'description' => $spec['description'] ?? null,
                'category' => $spec['category'] ?? null,
                'priority' => $spec['priority'] ?? 'medium',
                'assignee_id' => isset($spec['assignee']) ? $this->user($spec['assignee'])->id : null,
                'start_date' => isset($spec['start']) ? $this->date($spec['start']) : null,
                'due_date' => isset($spec['due']) ? $this->date($spec['due']) : null,
                'collaborator_ids' => array_map(fn ($u) => $this->user($u)->id, $spec['collaborators'] ?? []),
            ], $this->user($supervisor))->id;
        });

        foreach ($spec['log'] ?? [] as $n => $entry) {
            [$daysAgo, $value, $remark, $review] = $entry + [2 => null, 3 => false];
            $key = "{$title}#{$n}";

            $this->at($daysAgo, function () use ($title, $value, $remark, $key, $supervisor) {
                $task = $this->taskModel($title);
                $update = $this->tasks->applyUpdate(
                    $task,
                    $task->assignee ?? $this->user($supervisor),
                    is_string($value) ? $value : null,
                    is_int($value) ? $value : null,
                    $remark,
                );
                if ($id = $update?->meta['remark_id'] ?? null) {
                    $this->remarkIds[$key] = $id;
                }
            });

            if ($review && $remark) {
                $this->at(max($daysAgo - 1, 1), function () use ($key, $supervisor) {
                    $remark = Remark::find($this->remarkIds[$key] ?? 0);
                    if ($remark) {
                        $this->collaboration->review($remark, $this->user($supervisor));
                    }
                });
            }
        }

        foreach ($spec['comments'] ?? [] as [$daysAgo, $username, $body]) {
            $this->at($daysAgo, fn () => $this->collaboration->comment($this->taskModel($title), $body, [], $this->user($username)));
        }

        foreach ($spec['subtasks'] ?? [] as $subtask) {
            $this->task($subtask, $project, $title, $supervisor, $createdAt);
        }
    }

    /* ================================================================== Today */

    /**
     * Back in the present: run the deadline sweep (overdue → Delayed, due-soon reminders, project
     * health), then add the live touches — pinned focus tasks, "working on" sessions, read
     * notifications and recent sign-ins.
     */
    private function finishToday(): void
    {
        $this->deadlines->run();

        $pins = [
            'john.cruz' => 'Search and category filters',
            'sofia.aquino' => 'Food and beverage brands',
            'maria.reyes' => 'Content migration from the old site',
            'kevin.tan' => 'Prepare leasing brochure',
        ];
        foreach ($pins as $username => $title) {
            $this->collaboration->toggleFocus($this->taskModel($title), $this->user($username));
        }

        $working = [
            'john.cruz' => 'Search and category filters',
            'kevin.tan' => 'Prepare leasing brochure',
            'sofia.aquino' => 'Food and beverage brands',
        ];
        foreach ($working as $username => $title) {
            $this->collaboration->startWorking($this->taskModel($title), $this->user($username));
        }

        // Older notifications have been read; the last few days stay unread.
        DB::table('notifications')->where('created_at', '<', now()->subDays(3))->update(['read_at' => DB::raw('created_at')]);

        foreach (array_values($this->users) as $i => $user) {
            $user->forceFill(['last_login_at' => now()->subMinutes(($i * 97) % 4000 + 5), 'last_login_ip' => '127.0.0.1'])->save();
        }
    }

    /* ================================================================== Helpers */

    /** Queue an action for `$daysAgo` days back; same-day actions keep the order they were queued in. */
    private function at(int $daysAgo, Closure $action): void
    {
        $this->events[] = [$daysAgo, count($this->events), $action];
    }

    /** Run every queued action in time order with the clock set to its moment (working hours). */
    private function replay(): void
    {
        usort($this->events, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        $day = null;
        $minutes = 0;
        foreach ($this->events as [$daysAgo, $order, $action]) {
            if ($daysAgo !== $day) {
                [$day, $minutes] = [$daysAgo, 8 * 60 + 10];
            }
            $minutes += ($order * 17) % 33 + 9;

            Carbon::setTestNow($this->today->copy()->subDays($daysAgo)->addMinutes($minutes));
            $action();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<string> everyone assigned to or collaborating on these tasks
     */
    private function people(array $tasks): array
    {
        $people = [];
        foreach ($tasks as $task) {
            array_push($people, ...array_filter([$task['assignee'] ?? null, ...($task['collaborators'] ?? [])]));
            array_push($people, ...$this->people($task['subtasks'] ?? []));
        }

        return array_values(array_unique($people));
    }

    private function user(string $username): User
    {
        return $this->users[$username];
    }

    private function taskModel(string $title): Task
    {
        return Task::query()->findOrFail($this->taskIds[$title]);
    }

    private function projectModel(string $name): Project
    {
        return Project::query()->findOrFail($this->projectIds[$name]);
    }

    /** A date `$days` from today (negative = in the past). */
    private function date(int $days): string
    {
        return $this->today->copy()->addDays($days)->toDateString();
    }
}
