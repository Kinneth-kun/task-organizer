<?php

use App\Services\CollaborationService;
use App\Services\DeadlineService;
use App\Services\DigestService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Automation
|--------------------------------------------------------------------------
|
| Needs one cron entry on the server:   * * * * * php /path/to/artisan schedule:run
| (see docs/DEPLOYMENT.md). Times are in APP_TIMEZONE.
|
*/

Artisan::command('tasks:check-deadlines', function (DeadlineService $deadlines) {
    $result = $deadlines->run();
    $this->info("Marked {$result['delayed']} task(s) delayed, sent {$result['reminders']} reminder(s), refreshed {$result['projects']} project(s), expired {$result['sessions']} working session(s).");
})->purpose('Mark overdue tasks Delayed, send deadline reminders and refresh project health');

Artisan::command('digest:send {--dry-run : Show what would be sent without sending} {--force : Send even if disabled, quiet or already sent today} {--user= : One person (username, email or id)}', function (DigestService $digest) {
    $results = $digest->send([
        'dry_run' => (bool) $this->option('dry-run'),
        'force' => (bool) $this->option('force'),
        'user' => $this->option('user'),
    ]);

    foreach ($results as $row) {
        $this->line("<info>{$row['user']}</info>: {$row['status']}");
        if (! empty($row['title'])) {
            $this->line('  '.$row['title']);
            $this->line('  '.$row['message']);
            foreach ($row['lines'] ?? [] as $line) {
                $this->line('    • '.$line);
            }
        }
    }
})->purpose('Send the daily briefing notification');

Artisan::command('active-tasks:expire', function (CollaborationService $collaboration) {
    $this->info('Expired '.$collaboration->expireSessions().' working session(s).');
})->purpose('End "currently working" sessions older than a working day');

Schedule::command('tasks:check-deadlines')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('digest:send')->weekdays()->at('08:00');
Schedule::command('active-tasks:expire')->hourly();
// Sends queued emails on hosts without a long-running queue worker.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
